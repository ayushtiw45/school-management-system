<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolProvisioner;
use App\Support\Tenant;
use Illuminate\Support\Facades\Http;

class SaasTest extends SchoolTestCase
{
    private function otherSchool(bool $active = true): User
    {
        $admin = app(SchoolProvisioner::class)->create(
            ['name' => 'Green Valley School', 'phone' => '9123456780', 'email' => 'gv@school.test', 'city' => 'Lucknow'],
            ['name' => 'GV Admin', 'email' => 'gvadmin@school.test', 'password' => 'password'],
        );
        if ($active) {
            $admin->school->extendUntil(now()->addMonth());
        }
        Tenant::set($this->school);

        return $admin;
    }

    private function signup(): User
    {
        Tenant::set(null);
        $this->post('/register', [
            'school_name' => 'Little Stars School', 'name' => 'Meena', 'phone' => '9988776655', 'email' => 'meena@stars.test',
            'city' => 'Agra', 'state' => 'Uttar Pradesh', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('billing'));

        return User::where('email', 'meena@stars.test')->first();
    }

    // ---------- data isolation ----------

    public function test_each_school_sees_only_its_own_data(): void
    {
        $this->admit(); // a student in the demo school
        $mine = Student::count();
        $theirs = $this->otherSchool();

        $this->actingAs($theirs)->get('/students')->assertOk()->assertDontSee('Aarav');
        $this->assertSame(0, Student::count(), 'other school must see no students');
        $this->assertSame(11, SchoolClass::count(), 'other school has its own default classes');

        Tenant::set($this->school);
        $this->assertSame($mine, Student::count());
    }

    public function test_cannot_open_or_change_another_schools_records_by_id(): void
    {
        $student = $this->admit();
        $payment = null;
        $theirs = $this->otherSchool();

        $this->actingAs($theirs)->get(route('students.show', $student))->assertNotFound();
        $this->actingAs($theirs)->get(route('students.edit', $student))->assertNotFound();
        $this->actingAs($theirs)->put(route('students.update', $student), ['first_name' => 'Hacked'])->assertNotFound();
        $this->actingAs($theirs)->delete(route('admissions.reject', $student))->assertNotFound();

        Tenant::set($this->school);
        $this->assertSame('Aarav', $student->fresh()->first_name);
    }

    public function test_cannot_use_another_schools_class_in_a_form(): void
    {
        $theirs = $this->otherSchool();
        $foreignClass = SchoolClass::where('name', 'Class 1')->first(); // demo school's class (tenant = demo)

        $this->actingAs($theirs)->post(route('admissions.store'), $this->admissionData(['school_class_id' => $foreignClass->id, 'section_id' => null]))
            ->assertSessionHasErrors('school_class_id');
    }

    public function test_staff_accounts_are_per_school(): void
    {
        $theirs = $this->otherSchool();
        $demoAccountant = $this->user('accountant');

        $this->actingAs($theirs)->get(route('users.index'))->assertOk()->assertDontSee($demoAccountant->email);
        $this->actingAs($theirs)->get(route('users.edit', $demoAccountant))->assertNotFound();
        $this->actingAs($theirs)->post(route('users.store'), ['name' => 'Acc', 'email' => 'acc@gv.test', 'role' => 'accountant', 'is_active' => 1, 'password' => 'secret123', 'password_confirmation' => 'secret123']);
        $this->assertSame($theirs->school_id, User::where('email', 'acc@gv.test')->first()->school_id);
    }

    public function test_receipt_and_admission_numbers_start_at_one_for_every_school(): void
    {
        $this->admit();
        $theirs = $this->otherSchool();
        $this->actingAs($theirs)->post(route('admissions.store'), array_merge($this->admissionData(), [
            'school_class_id' => Tenant::run($theirs->school, fn () => SchoolClass::where('name', 'Class 1')->first()->id),
            'section_id' => null,
        ]));
        $nos = Student::withoutGlobalScopes()->pluck('admission_no')->all();
        $this->assertCount(2, $nos);
        $this->assertSame(1, count(array_unique(array_map(fn ($n) => substr($n, -4), $nos))), 'both schools start at 0001');
    }

    // ---------- sign-up & lock ----------

    public function test_signup_creates_a_locked_school_with_defaults(): void
    {
        $user = $this->signup();
        $school = $user->school;

        $this->assertSame('admin', $user->role);
        $this->assertSame('locked', $school->accessState());
        Tenant::set($school);
        $this->assertSame(11, SchoolClass::count());
        $this->assertSame('Little Stars School', \App\Models\Setting::get('school_name'));

        // every function is locked until ₹1 is paid
        foreach (['/dashboard', '/students', '/admissions/create', '/collect-fee', '/reports', '/settings', '/users'] as $url) {
            $this->get($url)->assertRedirect(route('billing'));
        }
        $this->post(route('classes.store'), ['name' => 'Class 9'])->assertRedirect(route('billing'));
        $this->get(route('billing'))->assertOk()->assertSee('Start your 7-day trial for ₹1')->assertSee('bi-lock-fill', false);
    }

    public function test_expired_school_is_read_only(): void
    {
        $this->school->forceFill(['paid_until' => now()->subDay()])->save();
        $admin = $this->user();

        $this->actingAs($admin)->get('/students')->assertOk()->assertSee('Read-only mode');
        $this->actingAs($admin)->get(route('reports.show', 'summary'))->assertOk();
        $this->actingAs($admin)->post(route('classes.store'), ['name' => 'Class 9'])->assertRedirect(route('billing'));
    }

    public function test_blocked_school_is_logged_out(): void
    {
        $this->school->forceFill(['is_blocked' => true])->save();
        $this->actingAs($this->user())->get('/dashboard')->assertRedirect(route('login'));
    }

    // ---------- billing ----------

    public function test_one_rupee_trial_unlocks_and_monthly_charges_extend(): void
    {
        foreach (['razorpay_key_id' => 'rzp_test_x', 'razorpay_key_secret' => 'sec', 'razorpay_webhook_secret' => 'wh'] as $k => $v) {
            PlatformSetting::put($k, $v);
        }
        Http::fake([
            'api.razorpay.com/v1/plans' => Http::response(['id' => 'plan_599']),
            'api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_1']),
        ]);
        $user = $this->signup();
        $school = $user->school;

        $this->postJson(route('billing.start'))->assertOk()->assertJson(['subscription_id' => 'sub_1', 'key' => 'rzp_test_x']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/subscriptions') && $r['addons'][0]['item']['amount'] === 100
            && abs($r['start_at'] - now()->addDays(7)->timestamp) < 5 && $r['notes']['school_id'] === (string) $school->id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/plans') && $r['item']['amount'] === 59900);

        // forged signature does nothing
        $this->post(route('billing.verify'), ['razorpay_payment_id' => 'pay_1', 'razorpay_subscription_id' => 'sub_1', 'razorpay_signature' => 'x'])->assertSessionHas('error');
        $this->assertSame('locked', $school->fresh()->accessState());

        // ₹1 paid → unlocked for 7 days
        $sig = hash_hmac('sha256', 'pay_1|sub_1', 'sec');
        $this->post(route('billing.verify'), ['razorpay_payment_id' => 'pay_1', 'razorpay_subscription_id' => 'sub_1', 'razorpay_signature' => $sig])->assertRedirect(route('dashboard'));
        $school->refresh();
        $this->assertSame('active', $school->accessState());
        $this->assertTrue($school->trial_used);
        $this->assertTrue($school->paid_until->isSameDay(now()->addDays(7)));
        $this->get('/students')->assertOk();

        // day 7: ₹599 charged (webhook) → +1 month + 2 grace days; duplicate webhook is harmless
        $this->travel(7)->days();
        $body = json_encode(['event' => 'subscription.charged', 'payload' => ['subscription' => ['entity' => ['id' => 'sub_1', 'status' => 'active', 'current_end' => now()->addMonth()->timestamp]]]]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'wh')];
        $this->call('POST', '/razorpay/webhook', [], [], [], $headers, $body)->assertOk();
        $this->call('POST', '/razorpay/webhook', [], [], [], $headers, $body)->assertOk();
        $this->call('POST', '/razorpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'bad'], $body)->assertStatus(400);
        $this->assertTrue($school->fresh()->paid_until->isSameDay(now()->addMonth()->addDays(2)));
        $this->assertSame('active', Subscription::first()->status);

        // second subscription for the same school gets no ₹1 trial
        Subscription::first()->update(['status' => 'cancelled']);
        $this->assertFalse(app(\App\Services\Billing::class)->trialAvailable($school->fresh()));
    }

    public function test_only_school_admin_can_start_payment(): void
    {
        PlatformSetting::put('razorpay_key_id', 'k');
        PlatformSetting::put('razorpay_key_secret', 's');
        $this->actingAs($this->user('accountant'))->postJson(route('billing.start'))->assertForbidden();
    }

    // ---------- public website & owner ----------

    public function test_home_page_and_hindi(): void
    {
        Tenant::set(null);
        $this->get('/')->assertOk()->assertSee('Start for ₹1')->assertSee('₹599')->assertDontSee('Install for Windows');
        $this->get('/lang/hi');
        $this->get('/')->assertOk()->assertSee('नर्सरी से कक्षा 8 तक के लिए');
        foreach (['/privacy', '/terms', '/refund-policy', '/contact', '/register', '/login', '/forgot-password'] as $u) {
            $this->get($u)->assertOk();
        }
    }

    public function test_super_admin_panel(): void
    {
        $owner = User::where('email', 'owner@tds.test')->first();
        Tenant::set(null);
        $this->post('/login', ['email' => 'owner@tds.test', 'password' => 'password'])->assertRedirect(route('super.dashboard'));

        foreach (['/super', '/super/schools', '/super/schools/'.$this->school->id, '/super/subscriptions', '/super/settings', '/super/schools?export=csv'] as $u) {
            $this->get($u)->assertOk();
        }
        $this->get('/dashboard')->assertRedirect(route('super.dashboard'));

        $locked = $this->otherSchool(active: false)->school;
        $this->post(route('super.schools.extend', $locked), ['days' => '30'])->assertSessionHas('success');
        $this->assertSame('active', $locked->fresh()->accessState());

        $this->put('/super/settings', ['company_name' => 'TDS', 'trial_days' => 7, 'trial_price' => 1, 'monthly_price' => 599, 'razorpay_key_id' => 'rzp_live_x', 'razorpay_key_secret' => 'top-secret'])->assertSessionHas('success');
        $this->put('/super/settings', ['company_name' => 'TDS', 'trial_days' => 7, 'trial_price' => 1, 'monthly_price' => 599, 'razorpay_key_secret' => ''])->assertSessionHas('success');
        $this->assertSame('top-secret', PlatformSetting::get('razorpay_key_secret'));
        $this->get('/super/settings')->assertDontSee('top-secret');

        // school admins cannot open the owner panel
        $this->actingAs($this->user())->get('/super')->assertForbidden();
    }

    public function test_online_admission_form_belongs_to_one_school(): void
    {
        Tenant::set(null);
        $this->get('/apply/'.$this->school->slug)->assertOk()->assertSee('Sunrise Public School');
        $this->get('/apply/no-such-school')->assertNotFound();
        $locked = $this->otherSchool(active: false)->school;
        Tenant::set(null);
        $this->get('/apply/'.$locked->slug)->assertNotFound();
    }
}
