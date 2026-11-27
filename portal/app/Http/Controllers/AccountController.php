<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Setting;
use App\Services\LicenseIssuer;
use App\Services\Razorpay;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function index(Request $request, Razorpay $razorpay)
    {
        $user = $request->user();

        return view('account.index', [
            'user' => $user,
            'licenses' => $user->licenses()->get(),
            'payments' => $user->payments()->with('license')->get(),
            'plans' => LicenseIssuer::pricedPlans(),
            'razorpayReady' => $razorpay->configured(),
            'prefillId' => $request->query('installation_id'),
            'downloaded' => $user->downloads()->exists(),
            'trialDays' => Setting::get('trial_days'),
        ]);
    }

    /** Download requires an account; every download is logged for follow-up. */
    public function download(Request $request)
    {
        Download::create([
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
        ]);

        return redirect()->away(Setting::get('download_url'));
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
        ]);
        $user->update($data);

        return back()->with('success', __('Profile updated.'));
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $request->user()->update(['password' => $request->password]);

        return back()->with('success', __('Password changed.'));
    }
}
