<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LicenseIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'TDS-7K2P-QX9M-4HDA';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Setting::put('razorpay_key_id', 'rzp_test_abc');
        Setting::put('razorpay_key_secret', 'secret123');
        Setting::put('razorpay_webhook_secret', 'whsec');
        Http::fake([
            'api.razorpay.com/v1/plans' => Http::response(['id' => 'plan_599']),
            'api.razorpay.com/v1/subscriptions' => Http::sequence()->push(['id' => 'sub_1', 'status' => 'created'])->push(['id' => 'sub_2', 'status' => 'created']),
            'api.razorpay.com/v1/subscriptions/*/cancel' => Http::response(['id' => 'sub_1', 'status' => 'cancelled']),
        ]);
    }

    private function school(array $a = []): User
    {
        return User::create(array_merge(['name' => 'R', 'email' => uniqid().'@s.in', 'phone' => '9876543210', 'school_name' => 'Sunrise School', 'city' => 'Kanpur', 'password' => 'password1'], $a));
    }

    private function webhook(string $event, array $sub, array $payment = []): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => $event, 'payload' => ['subscription' => ['entity' => $sub]] + ($payment ? ['payment' => ['entity' => $payment]] : [])]);

        return $this->call('POST', '/razorpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec')], $body);
    }

    private function latestKeyPayload(): array
    {
        $key = $this->getJson('/api/license/'.self::ID)->json('key');
        [, $p] = explode('.', $key);

        return json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    }

    public function test_software_link_needs_login_and_shows_one_rupee_trial(): void
    {
        $this->get('/activate?installation_id='.self::ID)->assertRedirect('/login');
        $this->get('/login')->assertSee('Log in to activate your software');

        $this->actingAs($this->school())->get('/activate?installation_id='.strtolower(self::ID))
            ->assertOk()->assertSee('Start your 7-day trial for ₹1')->assertSee('₹599 per month');
    }

    public function test_full_autopay_lifecycle(): void
    {
        $u = $this->school();
        $this->actingAs($u);

        // 1. start: ₹1 add-on now, first ₹599 charge after 7 days
        $res = $this->postJson('/subscribe', ['installation_id' => self::ID])->assertOk()->assertJson(['subscription_id' => 'sub_1', 'key' => 'rzp_test_abc']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/plans') && $r['item']['amount'] === 59900 && $r['period'] === 'monthly');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/subscriptions') && $r['plan_id'] === 'plan_599'
            && $r['addons'][0]['item']['amount'] === 100 && abs($r['start_at'] - now()->addDays(7)->timestamp) < 5 && $r['notes']['installation_id'] === self::ID);

        // 2. ₹1 paid in the browser → trial license for 7 days, the software can fetch it
        $sig = hash_hmac('sha256', 'pay_1|sub_1', 'secret123');
        $this->post('/subscribe/verify', ['razorpay_payment_id' => 'pay_1', 'razorpay_subscription_id' => 'sub_1', 'razorpay_signature' => $sig])
            ->assertRedirect(route('activate', ['installation_id' => self::ID]))->assertSessionHas('activated');
        $this->assertSame('authenticated', Subscription::first()->status);
        $p = $this->latestKeyPayload();
        $this->assertSame(self::ID, $p['id']);
        $this->assertSame(now()->addDays(7)->toDateString(), $p['exp']);
        $this->assertSame('7-day trial', $p['plan']);

        // webhook for the same event does not create a second key
        $this->webhook('subscription.authenticated', ['id' => 'sub_1', 'status' => 'authenticated'])->assertOk();
        $this->assertSame(1, License::count());

        // 3. day 7: first ₹599 charged → licence until period end + 2 days grace
        $this->travel(7)->days();
        $end = now()->addMonth()->timestamp;
        $this->webhook('subscription.charged', ['id' => 'sub_1', 'status' => 'active', 'current_end' => $end], ['id' => 'pay_2', 'amount' => 59900])->assertOk();
        $sub = Subscription::first();
        $this->assertSame('active', $sub->status);
        $this->assertSame(1, $sub->paid_count);
        $this->assertSame(now()->addMonth()->addDays(2)->toDateString(), $this->latestKeyPayload()['exp']);

        // duplicate charged webhook: no extra key
        $this->webhook('subscription.charged', ['id' => 'sub_1', 'status' => 'active', 'current_end' => $end], ['id' => 'pay_2'])->assertOk();
        $this->assertSame(2, License::count());

        // 4. payment fails → halted; school cancels → no more keys
        $this->webhook('subscription.halted', ['id' => 'sub_1', 'status' => 'halted'])->assertOk();
        $this->assertSame('halted', $sub->fresh()->status);
        $this->get('/account')->assertOk()->assertSee('Stopped (payment failed)');
    }

    public function test_trial_only_once_per_account_and_per_computer(): void
    {
        $u = $this->school();
        $this->actingAs($u)->postJson('/subscribe', ['installation_id' => self::ID]);
        $this->webhook('subscription.authenticated', ['id' => 'sub_1', 'status' => 'authenticated']);
        $this->webhook('subscription.cancelled', ['id' => 'sub_1', 'status' => 'cancelled']);

        // same computer, new account → no ₹1 trial, first month charged immediately
        $this->actingAs($this->school())->get('/activate?installation_id='.self::ID)->assertSee('Activate for ₹599 per month')->assertDontSee('Start your 7-day trial');
        $this->postJson('/subscribe', ['installation_id' => self::ID])->assertOk()->assertJson(['subscription_id' => 'sub_2']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/subscriptions') && $r['plan_id'] === 'plan_599' && ! isset($r['addons']) && ! isset($r['start_at']));

        // same account, another computer → no trial either
        $this->actingAs($u)->get('/activate?installation_id=TDS-AAAA-BBBB-CCCC')->assertDontSee('Start your 7-day trial');
    }

    public function test_cannot_start_a_second_autopay_for_the_same_computer(): void
    {
        $this->actingAs($this->school())->postJson('/subscribe', ['installation_id' => self::ID]);
        $this->webhook('subscription.authenticated', ['id' => 'sub_1', 'status' => 'authenticated']);
        $this->postJson('/subscribe', ['installation_id' => self::ID])->assertStatus(422);
        $this->get('/activate?installation_id='.self::ID)->assertSee('Autopay is active');
    }

    public function test_forged_subscription_signature_is_rejected(): void
    {
        $this->actingAs($this->school())->postJson('/subscribe', ['installation_id' => self::ID]);
        $this->post('/subscribe/verify', ['razorpay_payment_id' => 'pay_1', 'razorpay_subscription_id' => 'sub_1', 'razorpay_signature' => 'x'])->assertSessionHas('error');
        $this->assertSame(0, License::count());
        $this->assertSame('created', Subscription::first()->status);
    }

    public function test_school_can_cancel_autopay(): void
    {
        $u = $this->school();
        $this->actingAs($u)->postJson('/subscribe', ['installation_id' => self::ID]);
        $this->webhook('subscription.authenticated', ['id' => 'sub_1', 'status' => 'authenticated']);
        $sub = Subscription::first();

        $this->actingAs($this->school())->post(route('subscriptions.cancel', $sub))->assertNotFound(); // not theirs
        $this->actingAs($u)->post(route('subscriptions.cancel', $sub))->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/subscriptions/sub_1/cancel'));
        $this->assertSame('cancelled', $sub->fresh()->status);
        // already-issued trial key still valid until it ends
        $this->assertNotNull($this->getJson('/api/license/'.self::ID)->json('key'));
    }

    public function test_license_api(): void
    {
        $this->getJson('/api/license/not-an-id')->assertNotFound();
        $this->getJson('/api/license/'.self::ID)->assertOk()->assertJson(['key' => null, 'activate_url' => route('activate', ['installation_id' => self::ID])]);

        app(LicenseIssuer::class)->issue(self::ID, 'S', '1y', 'manual');
        app(LicenseIssuer::class)->issue(self::ID, 'S', 'custom', 'manual', null, null, null, now()->addDays(3)->toDateString());
        $this->assertSame(now()->addYear()->subDay()->toDateString(), $this->getJson('/api/license/'.self::ID)->json('expires')); // newest expiry wins
    }

    public function test_plan_is_reused_and_recreated_when_price_changes(): void
    {
        $this->actingAs($this->school())->postJson('/subscribe', ['installation_id' => self::ID]);
        $this->assertSame('plan_599', Setting::get('razorpay_plan_id'));
        Setting::put('monthly_price', '699');
        $this->actingAs($this->school())->postJson('/subscribe', ['installation_id' => 'TDS-AAAA-BBBB-CCCC']);
        Http::assertSentCount(4); // plan, sub, plan (new price), sub
    }
}
