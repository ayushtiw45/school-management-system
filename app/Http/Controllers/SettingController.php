<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]);

        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'school_address' => ['nullable', 'string', 'max:300'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'school_email' => ['nullable', 'email', 'max:150'],
            'admission_prefix' => ['required', 'alpha_num', 'max:10'],
            'receipt_prefix' => ['required', 'alpha_num', 'max:10'],
            'late_fine_amount' => ['required', 'numeric', 'min:0'],
            'fee_due_day' => ['required', 'integer', 'min:1', 'max:28'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return back()->with('success', 'Settings saved.');
    }
}
