<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Minimal Razorpay client: Orders API, checkout signature and webhook signature checks. */
class Razorpay
{
    public function configured(): bool
    {
        return Setting::get('razorpay_key_id') && Setting::get('razorpay_key_secret');
    }

    public function keyId(): ?string
    {
        return Setting::get('razorpay_key_id');
    }

    /** @return array Razorpay order (id, amount, currency, ...) */
    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        $res = Http::withBasicAuth(Setting::get('razorpay_key_id'), Setting::get('razorpay_key_secret'))
            ->timeout(20)
            ->asJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => $notes,
            ]);

        if (! $res->successful() || ! $res->json('id')) {
            throw new RuntimeException('Razorpay order failed: '.($res->json('error.description') ?? $res->status()));
        }

        return $res->json();
    }

    /** Signature sent to the browser after a successful checkout. */
    public function validCheckoutSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) Setting::get('razorpay_key_secret'));

        return hash_equals($expected, $signature);
    }

    public function validWebhookSignature(string $body, ?string $signature): bool
    {
        $secret = Setting::get('razorpay_webhook_secret');
        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
