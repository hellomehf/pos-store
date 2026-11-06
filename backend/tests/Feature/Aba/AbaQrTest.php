<?php

namespace Tests\Feature\Aba;

use App\Models\AbaPayment;
use App\Models\PendingPayment;
use App\Models\User;
use App\Services\Aba\AbaConfig;
use App\Services\Aba\AbaSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for the dynamic ABA KHQR (QR API) flow.
 *
 * PayWay HTTP calls are mocked — no network required.
 */
class AbaQrTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'ff6c403b78551edb00e5d6f39f0c9b71a9c7444d';

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        AbaConfig::reset();

        config([
            'services.aba_payway.merchant_id' => 'ec480150',
            'services.aba_payway.api_key' => self::API_KEY,
            'services.aba_payway.purchase_url' => 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase',
            'services.aba_payway.env' => 'sandbox',
            'services.aba_payway.qr_lifetime' => 15,
            'services.aba_payway.qr_callback_url' => '',
        ]);

        $this->cashier = User::factory()->create(['role' => 'admin']);
    }

    private function pending(array $attributes = []): PendingPayment
    {
        return PendingPayment::create(array_merge([
            'session_id' => 'session-'.uniqid(),
            'expected_amount' => '1.00',
            'expected_amount_khr' => '4100.00',
            'currency' => 'USD',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ], $attributes));
    }

    private function fakeQr(array $overrides = [])
    {
        return Http::response(array_merge([
            'qrString' => '00020101021230510016abaakhppxxx@abaa',
            'qrImage' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
            'abapay_deeplink' => 'abamobilebank://ababank.com?type=payway&qrcode=000201',
            'app_store' => 'https://itunes.apple.com/app/aba-mobile-bank/id968860649',
            'play_store' => 'https://play.google.com/store/apps/details?id=com.paygo24.ibank',
            'amount' => 1.0,
            'currency' => 'USD',
            'status' => [
                'code' => '0',
                'message' => 'Success.',
                'tran_id' => 'KTEST000000001',
            ],
        ], $overrides), 200);
    }

    // -------------------------------------------------------------------------
    // POST /api/aba/create-qr
    // -------------------------------------------------------------------------

    #[Test]
    public function create_qr_returns_real_khqr_and_persists_it(): void
    {
        $pending = $this->pending();

        Http::fake([
            '*/generate-qr' => $this->fakeQr(),
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-qr', ['session_id' => $pending->session_id]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'tran_id',
                'amount',
                'currency',
                'qr_string',
                'qr_image',
                'deeplink',
                'expires_at',
            ]);

        $this->assertSame('00020101021230510016abaakhppxxx@abaa', $response->json('qr_string'));
        $this->assertStringStartsWith('data:image/png;base64,', $response->json('qr_image'));
        $this->assertSame('KTEST000000001', $response->json('tran_id'));

        $fresh = $pending->fresh();
        $this->assertSame('KTEST000000001', $fresh->payment_reference);
        $this->assertSame($response->json('qr_string'), $fresh->qr_string);
        $this->assertSame($response->json('qr_image'), $fresh->qr_image);
    }

    #[Test]
    public function create_qr_sends_a_valid_qr_api_hash(): void
    {
        $pending = $this->pending();

        Http::fake([
            '*/generate-qr' => function ($request) {
                $data = $request->data();

                $expected = AbaSigner::qrHash($data, self::API_KEY);

                // PayWay receives exactly the signed fields.
                Assert::assertSame($expected, $data['hash']);
                Assert::assertSame('abapay_khqr', $data['payment_option']);
                Assert::assertSame('1.00', $data['amount']);
                Assert::assertSame(AbaConfig::QR_IMAGE_TEMPLATE, $data['qr_image_template']);
                Assert::assertSame(15, $data['lifetime']);

                return $this->fakeQr();
            },
        ]);

        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-qr', ['session_id' => $pending->session_id])
            ->assertOk();
    }

    #[Test]
    public function create_qr_enforces_the_minimum_lifetime(): void
    {
        config(['services.aba_payway.qr_lifetime' => 1]);
        AbaConfig::reset();

        $pending = $this->pending();

        Http::fake([
            '*/generate-qr' => function ($request) {
                Assert::assertSame(3, $request->data()['lifetime']);

                return $this->fakeQr();
            },
        ]);

        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-qr', ['session_id' => $pending->session_id])
            ->assertOk();
    }

    #[Test]
    public function create_qr_uses_the_persisted_amount_not_client_input(): void
    {
        $pending = $this->pending(['expected_amount' => '12.34']);

        Http::fake([
            '*/generate-qr' => function ($request) {
                Assert::assertSame('12.34', $request->data()['amount']);

                return $this->fakeQr(['amount' => 12.34]);
            },
        ]);

        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-qr', [
                'session_id' => $pending->session_id,
                'amount' => '0.01', // must be ignored
            ])
            ->assertOk()
            ->assertJsonFragment(['amount' => 12.34]);
    }

    #[Test]
    public function create_qr_requires_authentication(): void
    {
        $pending = $this->pending();

        $this->postJson('/api/aba/create-qr', ['session_id' => $pending->session_id])
            ->assertUnauthorized();
    }

    #[Test]
    public function create_qr_returns_404_for_unknown_session(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-qr', ['session_id' => 'does-not-exist'])
            ->assertNotFound();
    }

    #[Test]
    public function create_qr_rejects_a_completed_session(): void
    {
        $pending = $this->pending(['status' => 'completed']);

        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-qr', ['session_id' => $pending->session_id])
            ->assertStatus(422);
    }

    #[Test]
    public function create_qr_surfaces_gateway_errors(): void
    {
        $pending = $this->pending();

        Http::fake([
            '*/generate-qr' => $this->fakeQr([
                'status' => ['code' => '23', 'message' => 'Payment option not enabled.'],
            ]),
        ]);

        $this->actingAs($this->cashier)
            ->postJson('/api/aba/create-qr', ['session_id' => $pending->session_id])
            ->assertStatus(502)
            ->assertJsonFragment(['success' => false]);

        $this->assertNull($pending->fresh()->payment_reference);
    }

    // -------------------------------------------------------------------------
    // GET /api/aba/qr-status/{session}
    // -------------------------------------------------------------------------

    #[Test]
    public function qr_status_marks_pending_payment_completed_when_approved(): void
    {
        $pending = $this->pending(['payment_reference' => 'KTEST000000002']);

        Http::fake([
            '*/check-transaction-2' => Http::response([
                'data' => [
                    'payment_status' => 'APPROVED',
                    'payment_status_code' => 0,
                    'payment_amount' => 1.0,
                ],
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'KTEST000000002'],
            ], 200),
        ]);

        $this->actingAs($this->cashier)
            ->getJson("/api/aba/qr-status/{$pending->session_id}")
            ->assertOk()
            ->assertJsonFragment(['status' => AbaPayment::STATUS_PAID]);

        $this->assertSame('completed', $pending->fresh()->status);
    }

    #[Test]
    public function qr_status_stays_pending_while_aba_reports_pending(): void
    {
        $pending = $this->pending(['payment_reference' => 'KTEST000000003']);

        Http::fake([
            '*/check-transaction-2' => Http::response([
                'data' => ['payment_status' => 'PENDING', 'payment_status_code' => 2],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ], 200),
        ]);

        $this->actingAs($this->cashier)
            ->getJson("/api/aba/qr-status/{$pending->session_id}")
            ->assertOk()
            ->assertJsonFragment(['status' => AbaPayment::STATUS_PENDING]);

        $this->assertSame('pending', $pending->fresh()->status);
    }

    #[Test]
    public function qr_status_marks_payment_failed_when_declined(): void
    {
        $pending = $this->pending(['payment_reference' => 'KTEST000000004']);

        Http::fake([
            '*/check-transaction-2' => Http::response([
                'data' => ['payment_status' => 'DECLINED', 'payment_status_code' => 3],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ], 200),
        ]);

        $this->actingAs($this->cashier)
            ->getJson("/api/aba/qr-status/{$pending->session_id}")
            ->assertOk()
            ->assertJsonFragment(['status' => AbaPayment::STATUS_FAILED]);

        $this->assertSame('failed', $pending->fresh()->status);
    }

    #[Test]
    public function qr_status_expires_an_unpaid_session_after_its_deadline(): void
    {
        $pending = $this->pending([
            'payment_reference' => 'KTEST000000005',
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            '*/check-transaction-2' => Http::response([
                'data' => ['payment_status' => 'PENDING', 'payment_status_code' => 2],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ], 200),
        ]);

        $this->actingAs($this->cashier)
            ->getJson("/api/aba/qr-status/{$pending->session_id}")
            ->assertOk()
            ->assertJsonFragment(['status' => 'EXPIRED']);

        $this->assertSame('expired', $pending->fresh()->status);
    }

    #[Test]
    public function qr_status_returns_404_for_unknown_session(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/aba/qr-status/does-not-exist')
            ->assertNotFound();
    }
}
