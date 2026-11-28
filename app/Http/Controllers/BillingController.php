<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Services\Billing;
use App\Services\Razorpay;
use App\Support\Tenant;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function show(Billing $billing)
    {
        $school = Tenant::school();

        return view('billing', [
            'school' => $school,
            'state' => $school->accessState(),
            'trial' => $billing->trialAvailable($school),
            'live' => $school->liveSubscription(),
            'subscriptions' => $school->subscriptions()->where('status', '!=', 'created')->get(),
            'ready' => $billing->ready(),
            'trialDays' => Billing::trialDays(),
            'trialPrice' => Billing::trialPaise() / 100,
            'monthlyPrice' => Billing::monthlyPaise() / 100,
            'whatsapp' => preg_replace('/\D/', '', (string) PlatformSetting::get('contact_whatsapp')),
        ]);
    }

    /** JSON for Razorpay checkout (subscription mode). */
    public function start(Request $request, Billing $billing, Razorpay $razorpay)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only the school admin can start the plan.');
        $school = Tenant::school();

        if (! $billing->ready()) {
            return response()->json(['message' => 'Online payment is not available right now. Please contact us.'], 503);
        }
        if ($school->liveSubscription()) {
            return response()->json(['message' => 'Autopay is already active for your school.'], 422);
        }

        try {
            $coupon = $billing->coupon($request->input('coupon'), $school);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $sub = $billing->start($school, $coupon);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not start the payment. Please try again in a minute.'], 502);
        }

        $user = $request->user();

        return response()->json([
            'key' => $razorpay->keyId(),
            'subscription_id' => $sub->razorpay_subscription_id,
            'name' => PlatformSetting::get('company_name'),
            'description' => ($sub->trial_amount
                ? (Billing::trialDays() + $sub->extra_days).'-day trial for ₹'.($sub->trial_amount / 100).', then ₹'.($sub->monthly_amount / 100).' per month'
                : ($sub->extra_days ? $sub->extra_days.' days free, then ' : '').'₹'.($sub->monthly_amount / 100).' per month').($coupon ? ' (coupon '.$coupon->code.')' : ''),
            'prefill' => ['name' => $user->name, 'email' => $user->email, 'contact' => $school->phone ?: $user->phone],
        ]);
    }

    /** Check a coupon code and show the new price (billing page). */
    public function coupon(Request $request, Billing $billing)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $school = Tenant::school();
        try {
            $coupon = $billing->coupon($request->input('code'), $school);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        if (! $coupon) {
            return response()->json(['ok' => false, 'message' => 'Enter a coupon code.'], 422);
        }
        $q = $billing->quote($school, $coupon);
        $msg = 'Coupon applied: '.$coupon->label().'. ';
        $msg .= $q['trial'] ? 'Pay ₹'.($q['trial_paise'] / 100).' now, '.$q['free_days'].' days free, then ' : ($q['free_days'] ? $q['free_days'].' days free, then ' : '');
        $msg .= '₹'.number_format($q['monthly'] / 100).' per month'.($q['monthly'] < $q['list'] ? ' instead of ₹'.number_format($q['list'] / 100) : '').'.';

        return response()->json(['ok' => true, 'code' => $coupon->code, 'message' => $msg,
            'button' => $q['trial'] ? 'Pay ₹'.($q['trial_paise'] / 100).' & start trial' : ($q['free_days'] ? 'Start '.$q['free_days'].' free days' : 'Pay ₹'.number_format($q['monthly'] / 100).' & activate')]);
    }

    public function verify(Request $request, Razorpay $razorpay, Billing $billing)
    {
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_subscription_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);
        $sub = Subscription::where('razorpay_subscription_id', $data['razorpay_subscription_id'])->where('school_id', Tenant::id())->firstOrFail();

        if (! $razorpay->validSubscriptionSignature($data['razorpay_payment_id'], $data['razorpay_subscription_id'], $data['razorpay_signature'])) {
            return redirect()->route('billing')->with('error', 'Payment could not be verified. If money was deducted, contact us with your payment ID.');
        }

        $billing->authenticated($sub->razorpay_subscription_id);

        return redirect()->route('dashboard')->with('success', 'Payment successful! All features are now unlocked.');
    }

    public function cancel(Request $request, Subscription $subscription, Billing $billing)
    {
        abort_unless($request->user()->isAdmin() && $subscription->school_id === Tenant::id(), 404);

        try {
            $billing->cancel($subscription);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not cancel right now. Please try again or contact us.');
        }

        return back()->with('success', 'Autopay cancelled. The software keeps working until the paid period ends.');
    }

    /** Razorpay webhook: keeps every school's access in sync even if the browser was closed. */
    public function webhook(Request $request, Razorpay $razorpay, Billing $billing)
    {
        if (! $razorpay->validWebhookSignature($request->getContent(), $request->header('X-Razorpay-Signature'))) {
            return response('invalid signature', 400);
        }

        $event = (string) $request->input('event');
        $sub = $request->input('payload.subscription.entity', []);
        if (str_starts_with($event, 'subscription.') && ! empty($sub['id'])) {
            match ($event) {
                'subscription.authenticated' => $billing->authenticated($sub['id']),
                'subscription.charged' => $billing->charged($sub['id'], $sub['current_end'] ?? null),
                default => $billing->statusChanged($sub['id'], (string) ($sub['status'] ?? '')),
            };
        }

        return response('ok');
    }
}
