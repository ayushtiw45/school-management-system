<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Marks a payment paid and issues its license exactly once (browser callback and webhook may both arrive). */
class PaymentProcessor
{
    public function __construct(private LicenseIssuer $issuer) {}

    public function markPaid(string $orderId, string $paymentId): ?Payment
    {
        return DB::transaction(function () use ($orderId, $paymentId) {
            $payment = Payment::where('razorpay_order_id', $orderId)->lockForUpdate()->first();
            if (! $payment) {
                return null;
            }
            if ($payment->status === 'paid') {
                return $payment;
            }

            $license = $this->issuer->issue(
                $payment->installation_id,
                $payment->user->school_name,
                $payment->plan,
                'online',
                $payment->user_id,
                null,
                'Razorpay '.$paymentId,
            );

            $payment->update([
                'status' => 'paid',
                'razorpay_payment_id' => $paymentId,
                'paid_at' => now(),
                'license_id' => $license->id,
            ]);

            return $payment;
        });
    }
}
