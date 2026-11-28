<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\School;
use App\Services\Billing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        return view('super.coupons', [
            'coupons' => Coupon::with('school')->withCount('redemptions')->latest('id')->get(),
            'coupon' => new Coupon(['discount_type' => 'percent', 'is_active' => true]),
            'schools' => School::orderBy('name')->get(['id', 'name']),
            'monthly' => Billing::monthlyPaise(),
            'recent' => \App\Models\CouponRedemption::with(['coupon', 'school'])->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $coupon = Coupon::create($this->validated($request));

        return redirect()->route('super.coupons')->with('success', "Coupon {$coupon->code} created: {$coupon->label()}.");
    }

    public function edit(Coupon $coupon)
    {
        return view('super.coupon-edit', ['coupon' => $coupon->load('redemptions.school'), 'schools' => School::orderBy('name')->get(['id', 'name']), 'monthly' => Billing::monthlyPaise()]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validated($request, $coupon));

        return redirect()->route('super.coupons')->with('success', "Coupon {$coupon->code} saved. Schools that already used it keep their old price.");
    }

    public function toggle(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', "Coupon {$coupon->code} ".($coupon->is_active ? 'switched on.' : 'switched off.'));
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->redemptions()->exists()) {
            return back()->with('error', 'This coupon has been used, so it cannot be deleted. Switch it off instead.');
        }
        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => strtoupper(preg_replace('/\s+/', '', (string) $request->code))]);
        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Z0-9_-]{3,30}$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'discount_type' => ['required', Rule::in(array_keys(Coupon::TYPES))],
            'discount_value' => ['nullable', 'numeric', 'min:0', Rule::when($request->discount_type === 'percent', ['max:100'])],
            'extra_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'school_id' => ['nullable', 'exists:schools,id'],
        ], ['code.regex' => 'Use 3–30 letters / numbers (dash and underscore allowed), e.g. DIWALI50.']);
        $data['discount_value'] = $data['discount_type'] === 'none' ? 0 : ($data['discount_value'] ?? 0);
        $data['extra_days'] = $data['extra_days'] ?? 0;
        $data['new_schools_only'] = $request->boolean('new_schools_only');
        if ($data['discount_value'] <= 0 && $data['extra_days'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['discount_value' => 'Give a discount or some free days (or both).']);
        }
        if ($data['discount_type'] === 'flat' && $data['discount_value'] * 100 >= Billing::monthlyPaise()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['discount_value' => 'The ₹ discount must be less than the monthly price (₹'.(Billing::monthlyPaise() / 100).'). For a free period use extra free days.']);
        }

        return $data;
    }
}
