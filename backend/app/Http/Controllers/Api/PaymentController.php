<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function generateQr(Request $request): JsonResponse
    {
        // 1. Core Sandbox Credentials
        $merchant_id = 'ec476985';
        $api_key = '8e78b8c8ba5f8a1215b71551e9b43ba8ee203362';
        $api_url = 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase';

        // 2. Request Parameters
        $invoice_number = $request->input('invoice_number', 'TXN' . now()->timezone('Asia/Phnom_Penh')->format('YmdHis') . rand(100, 999));
        $amount = number_format((float) $request->input('amount'), 2, '.', '');
        $currency = $request->input('currency', 'USD');
        $payment_option = 'abapay_khqr_deeplink';
        $req_time = now()->timezone('Asia/Phnom_Penh')->format('YmdHis');

        // 3. BUILD STRING SEQUENCE (PayWay v3 String Concatenation Pattern)
        $hash_sequence = $req_time . $merchant_id . $invoice_number . $amount . $payment_option . $currency;

        // 4. COMPUTE RAW BINARY HMAC-SHA512 THEN BASE64 ENCODE
        $raw_hmac = hash_hmac('sha512', $hash_sequence, $api_key, true);
        $secure_hash = base64_encode($raw_hmac);

        // 5. PAYLOAD MIRRORS HASH SEQUENCE EXACTLY — no extra fields
        $post_fields = [
            'req_time'       => $req_time,
            'merchant_id'    => $merchant_id,
            'tran_id'        => $invoice_number,
            'amount'         => $amount,
            'payment_option' => $payment_option,
            'currency'       => $currency,
            'hash'           => $secure_hash,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $api_url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway connection failed: ' . $curlError,
            ], 500);
        }

        $body = json_decode($raw, true);

        $statusCode = $body['status']['code'] ?? null;

        if ($httpCode >= 200 && $httpCode < 300 && $statusCode !== null && (int) $statusCode === 0) {
            $qrData = $body['status']['qr_data']
                ?? $body['status']['qr_string']
                ?? $body['qrString']
                ?? $body['qr_string']
                ?? $body['qr_data']
                ?? null;

            return response()->json([
                'success' => true,
                'status'  => $body['status'],
                'qr_data'  => $qrData,
                'qr_image' => $body['qrImage'] ?? $body['qr_image'] ?? null,
                'tran_id'  => $invoice_number,
                'amount'   => $amount,
                'currency' => $currency,
                'raw_response' => $body,
            ]);
        }

        $errMsg = $body['status']['message'] ?? $body['message'] ?? $body['description'] ?? $raw ?: 'Unknown error';

        return response()->json([
            'success' => false,
            'status'  => $body['status'] ?? null,
            'message' => 'Payment gateway error: ' . $errMsg,
            'raw_response' => $body,
        ], 422);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tran_id' => 'required|string',
        ]);

        $merchant_id = 'ec476985';
        $api_key = '8e78b8c8ba5f8a1215b71551e9b43ba8ee203362';
        $api_url = 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction';
        $req_time = now()->timezone('Asia/Phnom_Penh')->format('YmdHis');
        $tran_id = $validated['tran_id'];

        $hash_sequence = $req_time . $merchant_id . $tran_id;
        $secure_hash = base64_encode(hash_hmac('sha512', $hash_sequence, $api_key, true));

        $post_fields = [
            'req_time'    => $req_time,
            'merchant_id' => $merchant_id,
            'tran_id'     => $tran_id,
            'hash'        => $secure_hash,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $api_url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($post_fields),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            return response()->json([
                'success' => false,
                'message' => 'Payment status lookup failed: ' . $curlError,
                'tran_id' => $tran_id,
            ], 502);
        }

        $body = json_decode($raw, true);
        $paymentStatus = strtoupper((string) ($body['data']['payment_status'] ?? $body['payment_status'] ?? ''));
        $paymentStatusCode = $body['data']['payment_status_code'] ?? $body['payment_status_code'] ?? null;
        $approved = $paymentStatus === 'APPROVED' || (is_numeric($paymentStatusCode) && (int) $paymentStatusCode === 0);

        return response()->json([
            'success' => $httpCode >= 200 && $httpCode < 300,
            'approved' => $approved,
            'payment_status' => $paymentStatus ?: null,
            'payment_status_code' => $paymentStatusCode,
            'status' => $body['status'] ?? null,
            'tran_id' => $tran_id,
            'raw_response' => $body,
        ], $httpCode >= 200 && $httpCode < 300 ? 200 : 502);
    }
}
