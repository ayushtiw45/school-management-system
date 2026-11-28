<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\Http;

class CouponTest extends SchoolTestCase
{
    private function owner(): User
    {
        Tenant::set(null);
        $owner = User::where('email', 'owner@tds.test')->firstOrFail();
        $this->actingAs($owner);

        return $owner;
    }

    private function razorpay(): void
    {
        foreach (['razorpay_key_id' => 'rzp_test_x', 'razorpay_key_secret' => 'sec', 'razorpay_webhook_secret' => 'wh'] as $k => $v) {
            PlatformSetting::put($k, $v);
        }
        Http::fake([
            'api.razorpay.com/v1/plans' => Http::sequence()->push(['id' => 'plan_a'])->push(['id' => 'plan_b'])->push(['id' => 'plan_c']),
            'api.razorpay.com/v1/subscriptions' => Http::sequence()->push(['id' => 'sub_1'])->push(['id' => 'sub_2'])->push(['id' => 'sub_3']),
        ]);
    }

    private function signup(string $email = 'meena@stars.test'): User
    {
        Tenant::set(null);
        auth()->logout();
        $this->post('/register', ['school_name' => 'Stars '.$email, 'name' => 'Meena', 'phone' => '9988776655', 'email' => $email,
            'city' => 'Agra', 'state' => 'Uttar Pradesh', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect(route('billing'));

        return User::where('email', $email)->first();
    }

    public function test_owner_creates_edits_switches_off_and_deletes_coupons(): void
    {
        $this->owner();
        $this->get(route('super.coupons'))->assertOk()->assertSee('New coupon');
        $this->post(route('super.coupons.store'), ['code' => 'diwali 50', 'discount_type' => 'percent', 'discount_value' => 50, 'extra_days' => 10, 'max_uses' => 100])
            ->assertSessionHas('success');
        $c = Coupon::firstOrFail();
        $this->assertSame('DIWALI50', $c->code);
        $this->assertSame(29900, $c->apply(59900));
        $this->assertSame('50% off every month + 10 extra free days', $c->label());

        // checks
        $this->post(route('super.coupons.store'), ['code' => 'DIWALI50', 'discount_type' => 'flat', 'discount_value' => 10])->assertSessionHasErrors('code');
        $this->post(route('super.coupons.store'), ['code' => 'BIG', 'discount_type' => 'flat', 'discount_value' => 700])->assertSessionHasErrors('discount_value');
        $this->post(route('super.coupons.store'), ['code' => 'NOTHING', 'discount_type' => 'none'])->assertSessionHasErrors('discount_value');
        $this->post(route('super.coupons.store'), ['code' => 'FREE30', 'discount_type' => 'none', 'extra_days' => 30])->assertSessionHas('success');

        $this->put(route('super.coupons.update', $c), ['code' => 'DIWALI40', 'discount_type' => 'percent', 'discount_value' => 40])->assertSessionHas('success');
        $this->assertSame('DIWALI40', $c->fresh()->code);
        $this->post(route('super.coupons.toggle', $c))->assertSessionHas('success');
        $this->assertSame('Off', $c->fresh()->status());
        $this->get(route('super.coupons.edit', $c))->assertOk();
        $this->delete(route('super.coupons.destroy', $c))->assertSessionHas('success');
        $this->assertSame(1, Coupon::count());

        // schools cannot open the coupon page
        $this->actingAs($this->user('admin'))->get(route('super.coupons'))->assertStatus(403);
    }

    public function test_school_applies_coupon_pays_discounted_price_and_gets_extra_days(): void
    {
        $this->owner();
        Coupon::create(['code' => 'LAUNCH100', 'discount_type' => 'flat', 'discount_value' => 100, 'extra_days' => 23, 'max_uses' => 1]);
        $this->razorpay();

        $user = $this->signup();
        $school = $user->school;
        $this->get(route('billing'))->assertOk()->assertSee('Have a coupon code?');

        $this->postJson(route('billing.coupon'), ['code' => 'nope'])->assertStatus(422)->assertJson(['ok' => false, 'message' => 'This coupon code is not valid.']);
        $this->postJson(route('billing.coupon'), ['code' => 'launch100'])->assertOk()->assertJson(['ok' => true, 'code' => 'LAUNCH100'])
            ->assertJsonFragment(['message' => 'Coupon applied: ₹100 off every month + 23 extra free days. Pay ₹1 now, 30 days free, then ₹499 per month instead of ₹599.']);

        $this->postJson(route('billing.start'), ['coupon' => 'LAUNCH100'])->assertOk()->assertJson(['subscription_id' => 'sub_1']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/plans') && $r['item']['amount'] === 49900);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/subscriptions') && abs($r['start_at'] - now()->addDays(30)->timestamp) < 5
            && $r['addons'][0]['item']['amount'] === 100 && $r['notes']['coupon'] === 'LAUNCH100');
        $sub = Subscription::where('razorpay_subscription_id', 'sub_1')->firstOrFail();
        $this->assertSame(49900, $sub->monthly_amount);
        $this->assertSame(0, Coupon::first()->used_count, 'counted only after payment');

        $sig = hash_hmac('sha256', 'pay_1|sub_1', 'sec');
        $this->post(route('billing.verify'), ['razorpay_payment_id' => 'pay_1', 'razorpay_subscription_id' => 'sub_1', 'razorpay_signature' => $sig])->assertRedirect(route('dashboard'));
        $this->assertTrue($school->fresh()->paid_until->isSameDay(now()->addDays(30)));
        $this->assertSame(1, Coupon::first()->used_count);
        $this->assertSame(1, CouponRedemption::count());
        $this->get(route('billing'))->assertSee('coupon LAUNCH100');

        // max uses reached → another school is refused
        $this->signup('other@stars.test');
        $this->postJson(route('billing.coupon'), ['code' => 'LAUNCH100'])->assertStatus(422)->assertJsonFragment(['message' => 'This coupon has already been used the maximum number of times.']);
        $this->postJson(route('billing.start'), ['coupon' => 'LAUNCH100'])->assertStatus(422);

        // owner sees who used it
        $this->owner();
        $this->get(route('super.coupons'))->assertSee($school->name)->assertSee('₹499');
        $this->get(route('super.subscriptions'))->assertSee('LAUNCH100');
    }

    public function test_coupon_rules_expiry_school_only_and_new_schools_only(): void
    {
        $billing = app(\App\Services\Billing::class);
        $school = $this->school; // already paid (demo school)
        $other = \App\Models\School::create(['name' => 'Fresh', 'email' => 'f@x.test']);
        Coupon::create(['code' => 'OLD', 'discount_type' => 'percent', 'discount_value' => 10, 'valid_until' => now()->subDay()]);
        Coupon::create(['code' => 'SOON', 'discount_type' => 'percent', 'discount_value' => 10, 'valid_from' => now()->addDay()]);
        Coupon::create(['code' => 'MINE', 'discount_type' => 'percent', 'discount_value' => 10, 'school_id' => $other->id]);
        Coupon::create(['code' => 'NEWONLY', 'discount_type' => 'percent', 'discount_value' => 10, 'new_schools_only' => true]);
        Coupon::create(['code' => 'OFF', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => false]);
        Coupon::create(['code' => 'FREEMONTH', 'discount_type' => 'none', 'extra_days' => 30]);

        $msg = function (string $code, $s) use ($billing) {
            try {
                $billing->coupon($code, $s);

                return 'ok';
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }
        };
        $this->assertSame('This coupon has expired.', $msg('OLD', $school));
        $this->assertStringStartsWith('This coupon can be used from', $msg('SOON', $school));
        $this->assertSame('This coupon is not for your school.', $msg('MINE', $school));
        $this->assertSame('ok', $msg('MINE', $other));
        $this->assertSame('This coupon is only for new schools.', $msg('NEWONLY', $school));
        $this->assertSame('ok', $msg('NEWONLY', $other));
        $this->assertSame('This coupon code is not valid.', $msg('OFF', $school));

        // a paying school renewing with free days: no ₹1 trial, first charge after 30 days
        $q = $billing->quote($school, Coupon::where('code', 'FREEMONTH')->first());
        $this->assertFalse($q['trial']);
        $this->assertSame(30, $q['free_days']);
        $this->assertSame(59900, $q['monthly']);
    }
}
