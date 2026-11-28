<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    private const FIELDS = [
        'school_name' => ['required', 'string', 'max:150'],
        'school_legal_name' => ['nullable', 'string', 'max:200'],
        'school_address' => ['nullable', 'string', 'max:300'],
        'school_state' => ['nullable', 'string', 'max:60'],
        'school_phone' => ['nullable', 'string', 'max:50'],
        'school_email' => ['nullable', 'email', 'max:150'],
        'school_website' => ['nullable', 'string', 'max:150'],
        'signatory_name' => ['nullable', 'string', 'max:100'],
        'signatory_title' => ['nullable', 'string', 'max:60'],
        'bank_account_name' => ['nullable', 'string', 'max:150'],
        'bank_account_no' => ['nullable', 'regex:/^[0-9A-Za-z ]{6,30}$/'],
        'bank_ifsc' => ['nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
        'bank_name' => ['nullable', 'string', 'max:150'],
        'upi_id' => ['nullable', 'regex:/^[A-Za-z0-9.\-_]{2,256}@[A-Za-z]{2,64}$/'],
        'receipt_note' => ['nullable', 'string', 'max:300'],
        'receipt_terms' => ['nullable', 'string', 'max:2000'],
        'slip_terms' => ['nullable', 'string', 'max:2000'],
        'admission_prefix' => ['required', 'alpha_num', 'max:10'],
        'receipt_prefix' => ['required', 'alpha_num', 'max:10'],
        'student_code_prefix' => ['required', 'alpha_num', 'max:8'],
        'late_fine_amount' => ['required', 'numeric', 'min:0'],
        'fee_due_day' => ['required', 'integer', 'min:1', 'max:28'],
    ];

    public function edit()
    {
        $settings = collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]);

        return view('settings.edit', ['settings' => $settings, 'states' => self::STATES]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(self::FIELDS, [
            'bank_ifsc.regex' => 'IFSC looks wrong (11 characters, e.g. SBIN0001234).',
            'upi_id.regex' => 'UPI ID looks wrong (e.g. schoolname@okaxis).',
            'bank_account_no.regex' => 'Account number looks wrong.',
        ]);
        $data['bank_ifsc'] = strtoupper((string) ($data['bank_ifsc'] ?? ''));
        $data['student_code_prefix'] = strtoupper($data['student_code_prefix']);

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }
        Tenant::school()?->update(['name' => $data['school_name']]);

        return back()->with('success', 'Settings saved.');
    }

    public function uploadLogo(Request $request)
    {
        return $this->storeImage($request, 'logo_path', 'logo', 'Logo');
    }

    public function uploadSignature(Request $request)
    {
        return $this->storeImage($request, 'signature_path', 'signature', 'Signature');
    }

    private function storeImage(Request $request, string $key, string $name, string $label)
    {
        if ($request->boolean('remove')) {
            if ($old = Setting::get($key)) {
                Storage::delete($old);
            }
            Setting::put($key, '');

            return back()->with('success', "{$label} removed.");
        }
        $request->validate(['image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024']],
            ['image.max' => 'The image must be smaller than 1 MB.']);

        $old = Setting::get($key);
        $path = $request->file('image')->storeAs('schools/'.Tenant::id(), $name.'-'.time().'.'.$request->file('image')->extension());
        Setting::put($key, $path);
        if ($old && $old !== $path) {
            Storage::delete($old);
        }

        return back()->with('success', "{$label} uploaded.");
    }

    public const STATES = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh',
        'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
        'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands',
        'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'];
}
