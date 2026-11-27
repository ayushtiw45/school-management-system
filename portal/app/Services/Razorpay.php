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

    private function api()
    {
        return Http::withBasicAuth(Setting::get('razorpay_key_id'), Setting::get('razorpay_key_secret'))->timeout(20)->asJson();
    }

    /** Monthly plan for the configured price; created once in Razorpay and remembered. */
    public function monthlyPlanId(int $amountPaise, string $name): string
    {
        if (Setting::get('razorpay_plan_id') && (int) Setting::get('razorpay_plan_amount') === $amountPaise) {
            return Setting::get('razorpay_plan_id');
        }

        $res = $this->api()->post('https://api.razorpay.com/v1/plans', [
            'period' => 'monthly',
            'interval' => 1,
            'item' => ['name' => $name, 'amount' => $amountPaise, 'currency' => 'INR', 'description' => 'Monthly license'],
        ]);
        if (! $res->successful() || ! $res->json('id')) {
            throw new RuntimeException('Razorpay plan failed: '.($res->json('error.description') ?? $res->status()));
        }
        Setting::put('razorpay_plan_id', $res->json('id'));
        Setting::put('razorpay_plan_amount', (string) $amountPaise);

        return $res->json('id');
    }

    /**
     * Subscription with an optional upfront trial charge: the school pays the add-on now (e.g. ₹1) while
     * authorising Autopay, and the first monthly charge happens at $startAt.
     */
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
        return hash_equals(hash_hmac('sha256', $paymentId.'|'.$subscriptionId, (string) Setting::get('razorpay_key_secret')), $signature);
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
