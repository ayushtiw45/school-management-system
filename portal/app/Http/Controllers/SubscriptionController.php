<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\LicenseIssuer;
use App\Services\Razorpay;
use App\Services\SubscriptionManager;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** Opened from the software: "Start 7-day trial for ₹1". */
    public function activate(Request $request, SubscriptionManager $manager, Razorpay $razorpay)
    {
        $id = LicenseIssuer::normalizeId((string) $request->query('installation_id'));
        $valid = (bool) preg_match(LicenseIssuer::ID_PATTERN, $id);

        return view('account.activate', [
            'installationId' => $valid ? $id : null,
            'trial' => $valid && $manager->trialAvailable($request->user(), $id),
            'live' => $valid ? $manager->liveSubscription($id) : null,
            'expiry' => $valid ? app(LicenseIssuer::class)->currentExpiry($id) : null,
            'ready' => $razorpay->configured() && SubscriptionManager::monthlyPaise() > 0,
            'trialDays' => SubscriptionManager::trialDays(),
            'trialPrice' => SubscriptionManager::trialPaise() / 100,
            'monthlyPrice' => SubscriptionManager::monthlyPaise() / 100,
        ]);
    }

    /** JSON for Razorpay checkout (subscription mode). */
    public function start(Request $request, SubscriptionManager $manager, Razorpay $razorpay)
    {
        $request->merge(['installation_id' => LicenseIssuer::normalizeId((string) $request->installation_id)]);
        $data = $request->validate(['installation_id' => ['required', 'regex:'.LicenseIssuer::ID_PATTERN]],
            ['installation_id.regex' => __('Installation ID looks wrong. Copy it from the License screen in the software (example: TDS-7K2P-QX9M-4HDA).')]);
        $id = $data['installation_id'];

        if (! $razorpay->configured() || SubscriptionManager::monthlyPaise() <= 0) {
            return response()->json(['message' => __('Online payment is not available right now.')], 503);
        }
        if ($manager->liveSubscription($id)) {
            return response()->json(['message' => __('Autopay is already active for this computer.')], 422);
        }
        if (app(LicenseIssuer::class)->currentExpiry($id) === 'life') {
            return response()->json(['message' => __('This computer already has a lifetime license.')], 422);
        }

        try {
            $sub = $manager->start($request->user(), $id);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not start the payment. Please try again in a minute.')], 502);
        }

        $user = $request->user();

        return response()->json([
            'key' => $razorpay->keyId(),
            'subscription_id' => $sub->razorpay_subscription_id,
            'name' => config('app.name'),
            'description' => $sub->trial_amount
                ? __(':days-day trial for ₹:trial, then ₹:monthly per month', ['days' => SubscriptionManager::trialDays(), 'trial' => $sub->trial_amount / 100, 'monthly' => $sub->monthly_amount / 100])
                : __('₹:monthly per month', ['monthly' => $sub->monthly_amount / 100]),
            'prefill' => ['name' => $user->name, 'email' => $user->email, 'contact' => $user->phone],
        ]);
    }

    public function verify(Request $request, Razorpay $razorpay, SubscriptionManager $manager)
    {
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_subscription_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);
        $sub = Subscription::where('razorpay_subscription_id', $data['razorpay_subscription_id'])->where('user_id', $request->user()->id)->firstOrFail();

        if (! $razorpay->validSubscriptionSignature($data['razorpay_payment_id'], $data['razorpay_subscription_id'], $data['razorpay_signature'])) {
            return redirect()->route('activate', ['installation_id' => $sub->installation_id])
                ->with('error', __('Payment could not be verified. If money was deducted, contact us with your payment ID.'));
        }

        $manager->authenticated($sub->razorpay_subscription_id);

        return redirect()->route('activate', ['installation_id' => $sub->installation_id])->with('activated', true);
    }

    public function cancel(Request $request, Subscription $subscription, SubscriptionManager $manager)
    {
        abort_unless($subscription->user_id === $request->user()->id || $request->user()->is_admin, 404);

        try {
            $manager->cancel($subscription);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Could not cancel right now. Please try again or contact us.'));
        }

        return back()->with('success', __('Autopay cancelled. The software keeps working until the paid period ends.'));
    }
}
