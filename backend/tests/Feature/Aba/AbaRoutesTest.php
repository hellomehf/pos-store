<?php

namespace Tests\Feature\Aba;

use PHPUnit\Framework\Attributes\Test;

use App\Models\AbaPayment;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Aba\AbaConfig;
use App\Services\Aba\AbaPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for ABA PayWay HTTP routes.
 *
 * PayWay HTTP calls are always mocked — no network is required.
 */
class AbaRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        AbaConfig::reset();

        // Set env so AbaConfig doesn't throw.
        config([
            'services.aba_payway.merchant_id'  => 'ec480150',
            'services.aba_payway.api_key'       => 'ff6c403b78551edb00e5d6f39f0c9b71a9c7444d',
            'services.aba_payway.purchase_url'  => 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase',
            'services.aba_payway.env'           => 'sandbox',
            'services.aba_payway.return_url'    => '',
            'services.aba_payway.cancel_url'    => '',
            'services.aba_payway.continue_success_url' => '',
        ]);

        $this->cashier = User::factory()->create(['role' => 'admin']);

        $category = Category::factory()->create();
        $product  = Product::factory()->create([
            'name'      => 'Widget',
            'price'     => 5.00,
            'stock_qty' => 100,
            'category_id' => $category->id,
        ]);

        $this->sale = Sale::create([
            'user_id'        => $this->cashier->id,
            'invoice_number' => 'INV-TEST-001',
            'subtotal'       => 5.00,
            'tax_rate'       => 0,
            'tax_amount'     => 0,
            'total'          => 5.00,
            'payment_method' => 'aba',
            'tendered_amount'=> 5.00,
            'change_amount'  => 0,
        ]);

        SaleItem::create([
            'sale_id'      => $this->sale->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'quantity'     => 1,
            'unit_price'   => 5.00,
            'line_total'   => 5.00,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/aba/create-payment
    // -------------------------------------------------------------------------

    #[Test]
    public function create_payment_returns_signed_fields(): void
    {
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-payment', ['order_id' => $this->sale->id]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'fields' => ['req_time', 'merchant_id', 'tran_id', 'amount', 'currency', 'hash', 'payment_gate'],
            ]);

        $data = $response->json('fields');
        $this->assertSame('5.00', $data['amount'], 'Amount must come from DB, not client');
        $this->assertSame('USD', $data['currency']);
        $this->assertLessThanOrEqual(20, strlen($data['tran_id']));
        $this->assertSame(0, $data['payment_gate']);
    }

    #[Test]
    public function create_payment_persists_pending_record(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-payment', ['order_id' => $this->sale->id])
            ->assertOk();

        $this->assertDatabaseHas('aba_payments', [
            'order_id' => $this->sale->id,
            'status'   => AbaPayment::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function create_payment_ignores_client_amount(): void
    {
        // The client sends a spoofed amount — the endpoint must use the DB amount.
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-payment', [
                'order_id' => $this->sale->id,
                'amount'   => '0.01', // tampered amount
            ]);

        $response->assertOk();
        $this->assertSame('5.00', $response->json('fields.amount'));
    }

    #[Test]
    public function create_payment_requires_valid_order_id(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-payment', ['order_id' => 99999])
            ->assertStatus(422);
    }

    #[Test]
    public function create_payment_requires_authentication(): void
    {
        $this->postJson('/api/aba/create-payment', ['order_id' => $this->sale->id])
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // POST /api/aba/check-transaction
    // -------------------------------------------------------------------------

    #[Test]
    public function check_transaction_returns_paid_for_approved_response(): void
    {
        Http::fake([
            '*/check-transaction-2' => Http::response([
                'status' => ['code' => 0, 'message' => 'Success'],
                'data'   => [
                    'payment_status'      => 'APPROVED',
                    'payment_status_code' => 0,
                ],
            ], 200),
        ]);

        $payment = AbaPayment::create([
            'tran_id'  => 'P1234567890',
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/aba/check-transaction', ['tran_id' => $payment->tran_id]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'PAID']);

        $this->assertDatabaseHas('aba_payments', [
            'tran_id' => $payment->tran_id,
            'status'  => AbaPayment::STATUS_PAID,
        ]);
    }

    #[Test]
    public function check_transaction_returns_failed_for_declined_response(): void
    {
        Http::fake([
            '*/check-transaction-2' => Http::response([
                'status' => ['code' => 0],
                'data'   => ['payment_status' => 'DECLINED'],
            ], 200),
        ]);

        $payment = AbaPayment::create([
            'tran_id'  => 'P1234567891',
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/aba/check-transaction', ['tran_id' => $payment->tran_id]);

        $response->assertOk()->assertJsonFragment(['status' => 'FAILED']);
    }

    #[Test]
    public function check_transaction_returns_pending_when_in_progress(): void
    {
        Http::fake([
            '*/check-transaction-2' => Http::response([
                'status' => ['code' => 0],
                'data'   => ['payment_status' => 'IN_PROGRESS'],
            ], 200),
        ]);

        $payment = AbaPayment::create([
            'tran_id'  => 'P1234567892',
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/aba/check-transaction', ['tran_id' => $payment->tran_id]);

        $response->assertOk()->assertJsonFragment(['status' => 'PENDING']);
    }

    #[Test]
    public function check_transaction_returns_502_on_timeout(): void
    {
        Http::fake([
            '*/check-transaction-2' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28'),
        ]);

        $payment = AbaPayment::create([
            'tran_id'  => 'P1234567893',
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/aba/check-transaction', ['tran_id' => $payment->tran_id]);

        $response->assertStatus(502);
    }

    // -------------------------------------------------------------------------
    // Idempotency: second "paid" check must not double-update
    // -------------------------------------------------------------------------

    #[Test]
    public function second_paid_check_does_not_re_update_payment_or_sale(): void
    {
        Http::fake([
            '*/check-transaction-2' => Http::response([
                'status' => ['code' => 0],
                'data'   => ['payment_status' => 'APPROVED'],
            ], 200),
        ]);

        $payment = AbaPayment::create([
            'tran_id'  => 'P9999999999',
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PAID, // already paid
        ]);

        // Check again.
        $this->actingAs($this->cashier)
            ->postJson('/api/aba/check-transaction', ['tran_id' => $payment->tran_id])
            ->assertOk();

        // Status must still be PAID, timestamps unchanged within the same second.
        $fresh = $payment->fresh();
        $this->assertSame(AbaPayment::STATUS_PAID, $fresh->status);
    }

    // -------------------------------------------------------------------------
    // POST /api/aba/callback
    // -------------------------------------------------------------------------

    private function callbackHmac(string $payload): string
    {
        $apiKey = 'ff6c403b78551edb00e5d6f39f0c9b71a9c7444d';
        return base64_encode(hash_hmac('sha512', $payload, $apiKey, true));
    }

    #[Test]
    public function callback_with_valid_hmac_and_paid_transaction_marks_payment(): void
    {
        Http::fake([
            '*/check-transaction-2' => Http::response([
                'status' => ['code' => 0],
                'data'   => ['payment_status' => 'APPROVED'],
            ], 200),
        ]);

        $payment = AbaPayment::create([
            'tran_id'  => 'PCBK0000001',
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PENDING,
        ]);

        $payload = json_encode(['tran_id' => $payment->tran_id, 'status' => '0']);
        $header  = $this->callbackHmac($payload);

        $this->postJson('/api/aba/callback', json_decode($payload, true), [
            'X-PAYWAY-HMAC-SHA512' => $header,
            'Content-Type'         => 'application/json',
        ])->assertOk()->assertJsonFragment(['result' => 'PAID']);
    }

    #[Test]
    public function callback_with_invalid_hmac_is_rejected(): void
    {
        $payload = json_encode(['tran_id' => 'PCBK0000001', 'status' => '0']);

        $this->postJson('/api/aba/callback', json_decode($payload, true), [
            'X-PAYWAY-HMAC-SHA512' => 'invalid_signature',
            'Content-Type'         => 'application/json',
        ])->assertStatus(403);
    }

    #[Test]
    public function callback_with_missing_tran_id_returns_400(): void
    {
        $payload = json_encode(['status' => '0']); // no tran_id
        $header  = $this->callbackHmac($payload);

        $this->postJson('/api/aba/callback', json_decode($payload, true), [
            'X-PAYWAY-HMAC-SHA512' => $header,
        ])->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Error code 4 — duplicate tran_id
    // -------------------------------------------------------------------------

    #[Test]
    public function duplicate_tran_id_causes_db_unique_constraint_error(): void
    {
        AbaPayment::create([
            'tran_id'  => 'DUPTEST0001',
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PENDING,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AbaPayment::create([
            'tran_id'  => 'DUPTEST0001', // same tran_id
            'order_id' => $this->sale->id,
            'amount'   => '5.00',
            'currency' => 'USD',
            'status'   => AbaPayment::STATUS_PENDING,
        ]);
    }
}
