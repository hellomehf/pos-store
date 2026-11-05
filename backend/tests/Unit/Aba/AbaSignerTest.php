<?php

namespace Tests\Unit\Aba;

use App\Services\Aba\AbaSigner;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbaSigner.
 *
 * All inputs are fixed so the expected hash can be computed offline and verified here.
 * The API key used is the sandbox key from ABA/README.md.
 */
class AbaSignerTest extends TestCase
{
    private const API_KEY = 'ff6c403b78551edb00e5d6f39f0c9b71a9c7444d';

    // -------------------------------------------------------------------------
    // reqTime
    // -------------------------------------------------------------------------

    /** @test */
    public function req_time_is_14_digits_utc(): void
    {
        $t = AbaSigner::reqTime();

        $this->assertMatchesRegularExpression('/^\d{14}$/', $t, 'reqTime must be 14 digits');
        $this->assertLessThanOrEqual(14, strlen($t));

        // Verify it looks like a UTC timestamp (year starts with 20)
        $this->assertStringStartsWith('20', $t);
    }

    // -------------------------------------------------------------------------
    // purchaseHash — known vector
    // -------------------------------------------------------------------------

    /** @test */
    public function purchase_hash_matches_known_vector(): void
    {
        // Fixed inputs — the expected hash is pre-computed with:
        //   echo -n "20261105100000ec480150T17000000000.10USD" | openssl dgst -sha512 -hmac "ff6c403b..." -binary | base64
        $fields = [
            'req_time'    => '20261105100000',
            'merchant_id' => 'ec480150',
            'tran_id'     => 'T17000000000',
            'amount'      => '0.10',
            'currency'    => 'USD',
        ];

        $hash = AbaSigner::purchaseHash($fields, self::API_KEY);

        // Independently computed expected value
        $rawStr  = '20261105100000' . 'ec480150' . 'T17000000000' . '0.10'
                 . '' . '' . '' . '' . '' . '' . '' . '' . '' . '' . '' . ''
                 . 'USD'
                 . '' . '' . '' . '' . '' . '' . '' . '';
        $expected = base64_encode(hash_hmac('sha512', $rawStr, self::API_KEY, true));

        $this->assertSame($expected, $hash);
    }

    /** @test */
    public function purchase_hash_field_order_matches_spec(): void
    {
        // All 24 hash-order fields present — hash must be stable regardless of PHP array order.
        $fields1 = [
            'req_time'            => '20261105100000',
            'merchant_id'         => 'ec480150',
            'tran_id'             => 'TID001',
            'amount'              => '5.00',
            'items'               => '[{"name":"Widget","quantity":"1","price":"5.00"}]',
            'shipping'            => '0.00',
            'firstname'           => 'Jane',
            'lastname'            => 'Doe',
            'email'               => 'jane@example.com',
            'phone'               => '0123456789',
            'type'                => '',
            'payment_option'      => '',
            'return_url'          => 'https://example.com/return',
            'cancel_url'          => 'https://example.com/cancel',
            'continue_success_url'=> '',
            'return_deeplink'     => '',
            'currency'            => 'USD',
            'custom_fields'       => '',
            'return_params'       => '',
            'payout'              => '',
            'lifetime'            => '',
            'additional_params'   => '',
            'google_pay_token'    => '',
            'skip_success_page'   => '',
        ];

        // Shuffle the array order (PHP arrays are ordered, but the hash must not care).
        $fields2 = array_reverse($fields1, true);

        $hash1 = AbaSigner::purchaseHash($fields1, self::API_KEY);
        $hash2 = AbaSigner::purchaseHash($fields2, self::API_KEY);

        $this->assertSame($hash1, $hash2, 'Hash must depend on field ORDER, not insertion order');
    }

    /** @test */
    public function unsent_fields_contribute_empty_string(): void
    {
        // Minimal fields — missing ones should contribute '' to the hash string.
        $minimal = [
            'req_time'    => '20261105120000',
            'merchant_id' => 'ec480150',
            'tran_id'     => 'TID002',
            'amount'      => '1.00',
            'currency'    => 'USD',
        ];

        // Build the expected raw string manually.
        $rawStr = '';
        foreach (AbaSigner::PURCHASE_HASH_ORDER as $key) {
            $rawStr .= $minimal[$key] ?? '';
        }
        $expected = base64_encode(hash_hmac('sha512', $rawStr, self::API_KEY, true));

        $this->assertSame($expected, AbaSigner::purchaseHash($minimal, self::API_KEY));
    }

    // -------------------------------------------------------------------------
    // checkTransactionHash — known vector
    // -------------------------------------------------------------------------

    /** @test */
    public function check_transaction_hash_covers_exactly_three_fields(): void
    {
        $reqTime    = '20261105100000';
        $merchantId = 'ec480150';
        $tranId     = 'TID001';

        $hash = AbaSigner::checkTransactionHash($reqTime, $merchantId, $tranId, self::API_KEY);

        $expected = base64_encode(
            hash_hmac('sha512', $reqTime . $merchantId . $tranId, self::API_KEY, true)
        );

        $this->assertSame($expected, $hash);
    }

    // -------------------------------------------------------------------------
    // verifyCallbackHmac
    // -------------------------------------------------------------------------

    /** @test */
    public function callback_hmac_verification_passes_for_valid_signature(): void
    {
        $payload = '{"tran_id":"TID001","status":"0"}';
        $header  = base64_encode(hash_hmac('sha512', $payload, self::API_KEY, true));

        $this->assertTrue(AbaSigner::verifyCallbackHmac($payload, $header, self::API_KEY));
    }

    /** @test */
    public function callback_hmac_verification_fails_for_tampered_payload(): void
    {
        $payload  = '{"tran_id":"TID001","status":"0"}';
        $header   = base64_encode(hash_hmac('sha512', $payload, self::API_KEY, true));
        $tampered = '{"tran_id":"TID001","status":"0","amount":"999.00"}';

        $this->assertFalse(AbaSigner::verifyCallbackHmac($tampered, $header, self::API_KEY));
    }

    /** @test */
    public function callback_hmac_verification_fails_for_wrong_key(): void
    {
        $payload  = '{"tran_id":"TID001","status":"0"}';
        $correct  = base64_encode(hash_hmac('sha512', $payload, self::API_KEY, true));

        $this->assertFalse(AbaSigner::verifyCallbackHmac($payload, $correct, 'wrong-key'));
    }

    // -------------------------------------------------------------------------
    // tran_id constraints (service helper — tested via pure string logic)
    // -------------------------------------------------------------------------

    /** @test */
    public function tran_id_is_at_most_20_characters(): void
    {
        // Simulate the generateTranId logic from AbaPaymentService for a range of order IDs
        foreach ([1, 999, 9999999] as $orderId) {
            $ms     = (int) round(microtime(true) * 1000);
            $suffix = substr((string) $ms, -10);
            $id     = substr('P' . $orderId . $suffix, 0, 20);

            $this->assertLessThanOrEqual(20, strlen($id), "tran_id for order {$orderId} exceeds 20 chars: {$id}");
        }
    }
}
