<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const SECRETS = ['razorpay_key_secret', 'razorpay_webhook_secret'];

    public function edit()
    {
        $settings = collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => in_array($k, self::SECRETS) ? '' : Setting::get($k)]);
        $hasSecret = collect(self::SECRETS)->mapWithKeys(fn ($k) => [$k => (bool) Setting::get($k)]);

        return view('admin.settings', compact('settings', 'hasSecret'));
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
            'download_url' => ['required', 'url'],
            'price_1y' => ['nullable', 'numeric', 'min:1'],
            'price_2y' => ['nullable', 'numeric', 'min:1'],
            'price_life' => ['nullable', 'numeric', 'min:1'],
            'razorpay_key_id' => ['nullable', 'string', 'max:100'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:200'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:200'],
        ], ['contact_whatsapp.regex' => 'WhatsApp number: digits only with country code, e.g. 919876543210.']);

        foreach ($data as $k => $v) {
            // blank secret fields mean "keep the saved value"
            if (in_array($k, self::SECRETS) && ($v === null || $v === '')) {
                continue;
            }
            Setting::put($k, $v === null ? '' : (string) $v);
        }

        return back()->with('success', 'Settings saved.');
    }
}
