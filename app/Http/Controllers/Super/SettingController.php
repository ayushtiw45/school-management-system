<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const SECRETS = ['razorpay_key_secret', 'razorpay_webhook_secret'];

    private const EDITABLE = ['company_name', 'contact_phone', 'contact_whatsapp', 'contact_email', 'trial_days', 'trial_price', 'monthly_price',
        'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret'];

    public function edit()
    {
        $settings = collect(self::EDITABLE)->mapWithKeys(fn ($k) => [$k => in_array($k, self::SECRETS) ? '' : PlatformSetting::get($k)]);
        $hasSecret = collect(self::SECRETS)->mapWithKeys(fn ($k) => [$k => (bool) PlatformSetting::get($k)]);

        return view('super.settings', compact('settings', 'hasSecret'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_whatsapp' => ['nullable', 'regex:/^\d{10,15}$/'],
            'contact_email' => ['nullable', 'email'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:60'],
            'trial_price' => ['required', 'numeric', 'min:1', 'max:1000'],
            'monthly_price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'razorpay_key_id' => ['nullable', 'string', 'max:100'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:200'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:200'],
        ], ['contact_whatsapp.regex' => 'WhatsApp number: digits only with country code, e.g. 919876543210.']);

        foreach ($data as $k => $v) {
            if (in_array($k, self::SECRETS) && ($v === null || $v === '')) {
                continue; // blank = keep saved secret
            }
            PlatformSetting::put($k, $v === null ? '' : (string) $v);
        }

        return back()->with('success', 'Settings saved.');
    }
}
