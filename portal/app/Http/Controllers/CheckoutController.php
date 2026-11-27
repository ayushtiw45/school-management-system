<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\LicenseIssuer;
use App\Services\PaymentProcessor;
use App\Services\Razorpay;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    /** Step 1: create a Razorpay order for an installation + plan (JSON for checkout.js). */
    public function order(Request $request, Razorpay $razorpay, LicenseIssuer $issuer)
    {
        $plans = LicenseIssuer::pricedPlans();
        $request->merge(['installation_id' => LicenseIssuer::normalizeId((string) $request->installation_id)]);
        $data = $request->validate([
            'installation_id' => ['required', 'regex:'.LicenseIssuer::ID_PATTERN],
            'plan' => ['required', Rule::in(array_keys($plans))],
        ], ['installation_id.regex' => __('Installation ID looks wrong. Copy it from the License screen in the software (example: TDS-7K2P-QX9M-4HDA).')]);

        abort_unless($razorpay->configured(), 503, __('Online payment is not available right now.'));

        if ($issuer->currentExpiry($data['installation_id']) === 'life') {
            return response()->json(['message' => __('This computer already has a lifetime license.')], 422);
        }

        $user = $request->user();
        $amount = (int) round($plans[$data['plan']] * 100);

        try {
            $order = $razorpay->createOrder($amount, 'tds-'.$user->id.'-'.now()->timestamp, [
                'installation_id' => $data['installation_id'],
                'plan' => $data['plan'],
                'user_id' => (string) $user->id,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not start the payment. Please try again in a minute.')], 502);
        }

        Payment::create([
            'user_id' => $user->id,
            'installation_id' => $data['installation_id'],
            'plan' => $data['plan'],
            'amount' => $amount,
            'razorpay_order_id' => $order['id'],
        ]);

        return response()->json([
            'key' => $razorpay->keyId(),
            'order_id' => $order['id'],
            'amount' => $amount,
            'name' => config('app.name'),
            'description' => __(LicenseIssuer::PLANS[$data['plan']]['label']).' · '.$data['installation_id'],
            'prefill' => ['name' => $user->name, 'email' => $user->email, 'contact' => $user->phone],
        ]);
    }

    /** Step 2: browser returns after paying; verify signature and issue the key. */
    public function verify(Request $request, Razorpay $razorpay, PaymentProcessor $processor)
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $payment = Payment::where('razorpay_order_id', $data['razorpay_order_id'])->where('user_id', $request->user()->id)->firstOrFail();

        if (! $razorpay->validCheckoutSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            $payment->update(['status' => 'failed']);

            return redirect()->route('account')->with('error', __('Payment could not be verified. If money was deducted, contact us with your payment ID.'));
        }

        $processor->markPaid($data['razorpay_order_id'], $data['razorpay_payment_id']);

        return redirect()->route('account')->with('success', __('Payment successful! Your license key is ready below.'))->with('new_license', true);
    }

    /** Razorpay webhook (payment.captured / order.paid): issues the key even if the browser was closed. */
    public function webhook(Request $request, Razorpay $razorpay, PaymentProcessor $processor)
    {
        if (! $razorpay->validWebhookSignature($request->getContent(), $request->header('X-Razorpay-Signature'))) {
            return response('invalid signature', 400);
        }

        $event = $request->input('event');
        $entity = $request->input('payload.payment.entity');
        if (in_array($event, ['payment.captured', 'order.paid'], true) && ! empty($entity['order_id']) && ($entity['status'] ?? '') === 'captured') {
            $processor->markPaid($entity['order_id'], $entity['id']);
        }

        return response('ok');
    }
}
