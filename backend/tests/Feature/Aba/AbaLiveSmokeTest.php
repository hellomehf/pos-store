<?php

namespace Tests\Feature\Aba;

use App\Models\AbaPayment;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Aba\AbaPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Live sandbox smoke test.
 *
 * Skipped unless PAYWAY_LIVE_TEST=1.
 * Uses the sandbox key from ABA/README.md and real HTTP calls.
 *
 * Run with:
 *   PAYWAY_LIVE_TEST=1 php artisan test --filter=AbaLiveSmokeTest
 */
class AbaLiveSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!env('PAYWAY_LIVE_TEST')) {
            $this->markTestSkipped('Set PAYWAY_LIVE_TEST=1 to run live sandbox tests.');
        }

        config([
            'services.aba_payway.merchant_id'  => env('PAYWAY_MERCHANT_ID', 'ec480150'),
            'services.aba_payway.api_key'       => env('PAYWAY_API_KEY', 'ff6c403b78551edb00e5d6f39f0c9b71a9c7444d'),
            'services.aba_payway.purchase_url'  => env('PAYWAY_PURCHASE_URL', 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase'),
            'services.aba_payway.env'           => 'sandbox',
            'services.aba_payway.return_url'    => '',
            'services.aba_payway.cancel_url'    => '',
            'services.aba_payway.continue_success_url' => '',
        ]);
    }

    /** @test */
    public function purchase_endpoint_returns_200_with_checkout_html(): void
    {
        $cashier  = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $product  = Product::factory()->create([
            'name'      => 'Smoke Test Widget',
            'price'     => 0.10,
            'stock_qty' => 10,
            'category_id' => $category->id,
        ]);

        $sale = Sale::create([
            'user_id'        => $cashier->id,
            'invoice_number' => 'INV-SMOKE-001',
            'subtotal'       => 0.10,
            'tax_rate'       => 0,
            'tax_amount'     => 0,
            'total'          => 0.10,
            'payment_method' => 'aba',
            'tendered_amount'=> 0.10,
            'change_amount'  => 0,
        ]);

        SaleItem::create([
            'sale_id'      => $sale->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'quantity'     => 1,
            'unit_price'   => 0.10,
            'line_total'   => 0.10,
        ]);

        $service = new AbaPaymentService();
        $fields  = $service->createPayment($sale);

        $this->assertArrayHasKey('hash', $fields);
        $this->assertArrayHasKey('tran_id', $fields);
        $this->assertSame('0.10', $fields['amount']);

        // Post to PayWay sandbox and verify 200 HTML response.
        $response = Http::asForm()->post($fields['req_time'] ? config('services.aba_payway.purchase_url') : '', $fields);

        $this->assertSame(200, $response->status(), 'PayWay sandbox must return 200');
        $this->assertStringContainsString('html', strtolower($response->header('Content-Type')));
    }
}
