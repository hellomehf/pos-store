<?php

namespace Tests\Unit\Aba;

use PHPUnit\Framework\Attributes\Test;

use App\Models\AbaPayment;
use App\Services\Aba\AbaPaymentService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbaPaymentService::mapStatus().
 *
 * These are pure-logic tests: no DB, no HTTP.
 */
class AbaStatusMapTest extends TestCase
{
    private function service(): AbaPaymentService
    {
        // We need to bypass the constructor's config lookup.
        // Use reflection to create the object without calling __construct.
        /** @var AbaPaymentService $svc */
        $svc = (new \ReflectionClass(AbaPaymentService::class))->newInstanceWithoutConstructor();
        return $svc;
    }

    #[Test]
    public function maps_approved_payment_status_to_paid(): void
    {
        $body = [
            'status' => ['code' => 0],
            'data'   => ['payment_status' => 'APPROVED'],
        ];

        $this->assertSame(AbaPayment::STATUS_PAID, $this->service()->mapStatus($body));
    }

    #[Test]
    public function maps_payment_status_code_zero_to_paid(): void
    {
        $body = [
            'status' => ['code' => 0],
            'data'   => ['payment_status' => '', 'payment_status_code' => 0],
        ];

        $this->assertSame(AbaPayment::STATUS_PAID, $this->service()->mapStatus($body));
    }

    #[Test]
    public function maps_declined_to_failed(): void
    {
        foreach (['DECLINED', 'FAILED', 'CANCELLED', 'REJECTED'] as $payStatus) {
            $body = [
                'status' => ['code' => 0],
                'data'   => ['payment_status' => $payStatus],
            ];
            $this->assertSame(
                AbaPayment::STATUS_FAILED,
                $this->service()->mapStatus($body),
                "Expected FAILED for payment_status={$payStatus}"
            );
        }
    }

    #[Test]
    public function maps_unknown_status_to_pending(): void
    {
        $body = [
            'status' => ['code' => 0],
            'data'   => ['payment_status' => 'IN_PROGRESS'],
        ];

        $this->assertSame(AbaPayment::STATUS_PENDING, $this->service()->mapStatus($body));
    }

    #[Test]
    public function api_level_error_code_returns_pending(): void
    {
        // Non-zero status.code = API error (hash wrong, rate-limited, etc.)
        $body = [
            'status' => ['code' => 1, 'message' => 'Invalid hash'],
            'data'   => [],
        ];

        $this->assertSame(AbaPayment::STATUS_PENDING, $this->service()->mapStatus($body));
    }

    #[Test]
    public function empty_body_returns_pending(): void
    {
        $this->assertSame(AbaPayment::STATUS_PENDING, $this->service()->mapStatus([]));
    }

    #[Test]
    public function payment_status_comparison_is_case_insensitive(): void
    {
        $body = [
            'status' => ['code' => 0],
            'data'   => ['payment_status' => 'approved'], // lowercase
        ];

        $this->assertSame(AbaPayment::STATUS_PAID, $this->service()->mapStatus($body));
    }
}
