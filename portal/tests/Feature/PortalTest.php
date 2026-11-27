<?php

namespace Tests\Feature;

use App\Models\Download;
use App\Models\License;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'TDS-7K2P-QX9M-4HDA';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Setting::put('price_1y', '4999');
        Setting::put('price_life', '14999');
        Setting::put('razorpay_key_id', 'rzp_test_abc');
        Setting::put('razorpay_key_secret', 'secret123');
        Setting::put('razorpay_webhook_secret', 'whsec');
    }

    private function school(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Ramesh', 'email' => 'r'.uniqid().'@school.in', 'phone' => '9876543210',
            'school_name' => 'Sunrise School', 'city' => 'Kanpur', 'password' => 'password1',
        ], $attrs));
    }

    private function admin(): User
    {
        $u = $this->school(['school_name' => 'Administrator']);
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    public function test_home_page_is_the_company_page_in_english_and_hindi(): void
    {
        $this->get('/')->assertOk()->assertSee('TDS School')->assertSee('Install for Windows')->assertSee('₹4,999')->assertDontSee('Admin login');

        $this->get('/lang/hi')->assertRedirect();
        $this->get('/')->assertOk()->assertSee('विंडोज़ के लिए इंस्टॉल करें')->assertSee('नर्सरी से कक्षा 8 तक के लिए');

        $this->get('/lang/en');
        $this->get('/')->assertSee('Install for Windows');
        $this->get('/lang/xx')->assertNotFound();
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
    }

    public function test_install_for_windows_requires_signup_then_download_starts(): void
    {
        $this->get('/install-for-windows')->assertRedirect(route('register', ['next' => 'download']));
        $this->get(route('register', ['next' => 'download']))->assertOk()->assertSee('download starts right after sign up');

        $this->post('/register', [
            'school_name' => 'Green Valley School', 'name' => 'Sunita', 'phone' => '9123456780', 'email' => 'sunita@gv.in',
            'city' => 'Lucknow', 'state' => 'Uttar Pradesh', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('account', ['start_download' => 1]));

        $this->assertAuthenticated();
        $this->get(route('account', ['start_download' => 1]))->assertOk()->assertSee('Your download is starting')->assertSee('Green Valley School');

        $this->get('/download')->assertRedirect(Setting::get('download_url'));
        $this->assertSame(1, Download::count());
        $this->assertSame('sunita@gv.in', Download::first()->user->email);
    }

    public function test_download_needs_login(): void
    {
        $this->get('/download')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_register_validation_and_login(): void
    {
        $this->post('/register', ['email' => 'bad'])->assertSessionHasErrors(['school_name', 'name', 'phone', 'email', 'city', 'password']);

        $u = $this->school(['email' => 'a@b.in']);
        $this->post('/login', ['email' => 'a@b.in', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'a@b.in', 'password' => 'password1'])->assertRedirect(route('account'));
        $this->assertNotNull($u->fresh()->last_login_at);
    }

    public function test_online_purchase_issues_a_valid_key(): void
    {
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_123', 'amount' => 499900, 'currency' => 'INR'])]);
        $u = $this->school();

        $res = $this->actingAs($u)->postJson(route('checkout.order'), ['installation_id' => strtolower(self::ID), 'plan' => '1y']);
        $res->assertOk()->assertJson(['key' => 'rzp_test_abc', 'order_id' => 'order_123', 'amount' => 499900]);
        Http::assertSent(fn ($r) => $r['amount'] === 499900 && $r['notes']['installation_id'] === self::ID);

        $sig = hash_hmac('sha256', 'order_123|pay_999', 'secret123');
        $this->post(route('checkout.verify'), ['razorpay_order_id' => 'order_123', 'razorpay_payment_id' => 'pay_999', 'razorpay_signature' => $sig])
            ->assertRedirect(route('account'))->assertSessionHas('success');

        $payment = Payment::first();
        $this->assertSame('paid', $payment->status);
        $license = License::first();
        $this->assertSame(self::ID, $license->installation_id);
        $this->assertSame($u->id, $license->user_id);
        $this->assertTrue($license->expires_on->isSameDay(now()->addYear()->subDay()));

        // the key verifies with the public key, exactly like the desktop app does
        [$v, $p, $s] = explode('.', $license->license_key);
        $json = base64_decode(strtr($p, '-_', '+/'));
        $this->assertTrue(sodium_crypto_sign_verify_detached(base64_decode(strtr($s, '-_', '+/').'=='), $json, base64_decode(app(LicenseIssuer::class)->publicKey())));
        $this->assertSame(self::ID, json_decode($json, true)['id']);

        $this->get(route('account'))->assertSee($license->license_key);
    }

    public function test_forged_checkout_signature_is_rejected(): void
    {
        $u = $this->school();
        Payment::create(['user_id' => $u->id, 'installation_id' => self::ID, 'plan' => '1y', 'amount' => 499900, 'razorpay_order_id' => 'order_x']);

        $this->actingAs($u)->post(route('checkout.verify'), ['razorpay_order_id' => 'order_x', 'razorpay_payment_id' => 'pay_1', 'razorpay_signature' => 'forged'])
            ->assertSessionHas('error');
        $this->assertSame(0, License::count());
        $this->assertSame('failed', Payment::first()->status);
    }

    public function test_cannot_verify_someone_elses_order(): void
    {
        $owner = $this->school();
        Payment::create(['user_id' => $owner->id, 'installation_id' => self::ID, 'plan' => '1y', 'amount' => 499900, 'razorpay_order_id' => 'order_o']);
        $sig = hash_hmac('sha256', 'order_o|pay_1', 'secret123');

        $this->actingAs($this->school())->post(route('checkout.verify'), ['razorpay_order_id' => 'order_o', 'razorpay_payment_id' => 'pay_1', 'razorpay_signature' => $sig])->assertNotFound();
    }

    public function test_webhook_issues_key_once_even_if_browser_also_returns(): void
    {
        $u = $this->school();
        Payment::create(['user_id' => $u->id, 'installation_id' => self::ID, 'plan' => 'life', 'amount' => 1499900, 'razorpay_order_id' => 'order_w']);

        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_w', 'order_id' => 'order_w', 'status' => 'captured']]]]);
        $this->call('POST', '/razorpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'bad'], $body)->assertStatus(400);
        $this->assertSame(0, License::count());

        $good = hash_hmac('sha256', $body, 'whsec');
        $this->call('POST', '/razorpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $good], $body)->assertOk();
        $this->call('POST', '/razorpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $good], $body)->assertOk();
        $this->actingAs($u)->post(route('checkout.verify'), ['razorpay_order_id' => 'order_w', 'razorpay_payment_id' => 'pay_w', 'razorpay_signature' => hash_hmac('sha256', 'order_w|pay_w', 'secret123')]);

        $this->assertSame(1, License::count());
        $this->assertNull(License::first()->expires_on); // lifetime
    }

    public function test_renewal_starts_after_current_license_and_lifetime_blocks_more_purchases(): void
    {
        $issuer = app(LicenseIssuer::class);
        $first = $issuer->issue(self::ID, 'Sunrise', '1y', 'manual');
        $second = $issuer->issue(self::ID, 'Sunrise', '1y', 'manual');
        $this->assertTrue($second->starts_on->isSameDay($first->expires_on->copy()->addDay()));
        $this->assertTrue($second->expires_on->isSameDay(now()->addYears(2)->subDay()));

        $issuer->issue(self::ID, 'Sunrise', 'life', 'manual');
        $this->actingAs($this->school())->postJson(route('checkout.order'), ['installation_id' => self::ID, 'plan' => '1y'])
            ->assertStatus(422)->assertJson(['message' => 'This computer already has a lifetime license.']);
    }

    public function test_order_validation(): void
    {
        $u = $this->school();
        $this->actingAs($u)->postJson(route('checkout.order'), ['installation_id' => 'TDS-1234', 'plan' => '1y'])->assertStatus(422)->assertJsonValidationErrors('installation_id');
        $this->actingAs($u)->postJson(route('checkout.order'), ['installation_id' => self::ID, 'plan' => '2y'])->assertStatus(422)->assertJsonValidationErrors('plan'); // 2y has no price
    }

    public function test_buy_link_from_the_software_prefills_installation_id(): void
    {
        $this->get('/buy?installation_id='.self::ID)->assertRedirect('/login');
        $this->actingAs($this->school())->get('/buy?installation_id='.self::ID)->assertRedirect(route('account', ['installation_id' => self::ID]).'#buy');
        $this->get(route('account', ['installation_id' => self::ID]))->assertSee('value="'.self::ID.'"', false);
    }

    public function test_admin_pages_and_manual_license(): void
    {
        $school = $this->school();
        $this->actingAs($school)->get('/admin')->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin);
        foreach (['/admin', '/admin/schools', '/admin/schools/'.$school->id, '/admin/licenses', '/admin/payments', '/admin/settings', '/admin/signing-key'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/schools?export=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get('/admin/signing-key/backup')->assertOk()->assertSee('secret');

        $this->post('/admin/licenses', ['installation_id' => self::ID, 'school_name' => 'Sunrise', 'plan' => 'custom', 'custom_date' => now()->addMonths(3)->toDateString(), 'user_id' => $school->id])
            ->assertRedirect(route('admin.licenses'));
        $this->assertSame($school->id, License::first()->user_id);
        $this->assertTrue(License::first()->expires_on->isSameDay(now()->addMonths(3)));
    }

    public function test_admin_settings_keep_secrets_when_left_blank(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'company_name' => 'TDS', 'trial_days' => 30, 'download_url' => 'https://example.com/setup.exe',
            'price_1y' => 5999, 'price_2y' => '', 'price_life' => '', 'razorpay_key_id' => 'rzp_live_x', 'razorpay_key_secret' => '', 'contact_whatsapp' => '919876543210',
        ])->assertSessionHas('success');

        $this->assertSame('5999', Setting::get('price_1y'));
        $this->assertSame('secret123', Setting::get('razorpay_key_secret'));
        $this->assertEmpty(Setting::get('price_life')); // cleared → plan hidden
        $this->assertArrayNotHasKey('life', LicenseIssuer::pricedPlans());
        $this->get('/admin/settings')->assertDontSee('secret123');
    }

    public function test_password_reset_flow(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $u = $this->school(['email' => 'reset@school.in']);
        $this->post('/forgot-password', ['email' => 'reset@school.in'])->assertSessionHas('success');
        $this->post('/forgot-password', ['email' => 'nobody@school.in'])->assertSessionHas('success'); // no account enumeration

        \Illuminate\Support\Facades\Notification::assertSentTo($u, \Illuminate\Auth\Notifications\ResetPassword::class, function ($n) use ($u) {
            $this->post('/reset-password', ['token' => $n->token, 'email' => $u->email, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertRedirect(route('login'));

            return true;
        });
        $this->post('/login', ['email' => 'reset@school.in', 'password' => 'newpass123'])->assertRedirect();
    }
}
