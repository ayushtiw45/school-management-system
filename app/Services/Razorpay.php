<?php

namespace App\Services;

use App\Models\PlatformSetting as P;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Minimal Razorpay client for Subscriptions (Autopay) and signature checks. Keys are set in Super Admin → Settings. */
class Razorpay
{
    public function configured(): bool
    {
        return P::get('razorpay_key_id') && P::get('razorpay_key_secret');
    }

    public function keyId(): ?string
    {
        return P::get('razorpay_key_id');
    }

    private function api()
    {
        return Http::withBasicAuth((string) P::get('razorpay_key_id'), (string) P::get('razorpay_key_secret'))->timeout(20)->asJson();
    }

    /** Monthly plan for the configured price; created once in Razorpay and remembered. */
    public function monthlyPlanId(int $amountPaise, string $name): string
    {
        if (P::get('razorpay_plan_id') && (int) P::get('razorpay_plan_amount') === $amountPaise) {
            return P::get('razorpay_plan_id');
        }

        $res = $this->api()->post('https://api.razorpay.com/v1/plans', [
            'period' => 'monthly',
            'interval' => 1,
            'item' => ['name' => $name, 'amount' => $amountPaise, 'currency' => 'INR', 'description' => 'Monthly plan'],
        ]);
        if (! $res->successful() || ! $res->json('id')) {
            throw new RuntimeException('Razorpay plan failed: '.($res->json('error.description') ?? $res->status()));
        }
        P::put('razorpay_plan_id', $res->json('id'));
        P::put('razorpay_plan_amount', (string) $amountPaise);

        return $res->json('id');
    }

    /** Subscription with an optional upfront charge (₹1 trial) paid while authorising Autopay; first monthly charge at $startAt. */
    public function createSubscription(string $planId, ?int $startAt, int $upfrontPaise, string $upfrontName, array $notes): array
    {
        $body = ['plan_id' => $planId, 'total_count' => 120, 'customer_notify' => 1, 'notes' => $notes];
        if ($startAt) {
            $body['start_at'] = $startAt;
        }
        if ($upfrontPaise > 0) {
            $body['addons'] = [['item' => ['name' => $upfrontName, 'amount' => $upfrontPaise, 'currency' => 'INR']]];
        }

        $res = $this->api()->post('https://api.razorpay.com/v1/subscriptions', $body);
        if (! $res->successful() || ! $res->json('id')) {
            throw new RuntimeException('Razorpay subscription failed: '.($res->json('error.description') ?? $res->status()));
        }

        return $res->json();
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $res = $this->api()->post("https://api.razorpay.com/v1/subscriptions/{$subscriptionId}/cancel", ['cancel_at_cycle_end' => 0]);
        if (! $res->successful()) {
            throw new RuntimeException('Razorpay cancel failed: '.($res->json('error.description') ?? $res->status()));
        }
    }

    /** Checkout signature for subscriptions: hmac(payment_id|subscription_id). */
    public function validSubscriptionSignature(string $paymentId, string $subscriptionId, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $paymentId.'|'.$subscriptionId, (string) P::get('razorpay_key_secret')), $signature);
    }

    public function validWebhookSignature(string $body, ?string $signature): bool
    {
        $secret = P::get('razorpay_webhook_secret');

        return $secret && $signature && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
