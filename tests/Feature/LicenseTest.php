<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    /** What the fake website returns for /api/license/{id}: a key, null, or 'offline'. */
    private ?string $serverKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        $pair = sodium_crypto_sign_keypair();
        $this->secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        config([
            'license.enabled' => true,
            'license.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'license.trial_days' => 30,
        ]);
        $this->seed(DatabaseSeeder::class);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(function () {
            if ($this->serverKey === 'offline') {
                throw new \Illuminate\Http\Client\ConnectionException('offline');
            }

            return \Illuminate\Support\Facades\Http::response(['key' => $this->serverKey]);
        });
    }

    private function key(array $overrides = [], ?string $secret = null): string
    {
        return LicenseService::sign(array_merge([
            'id' => app(LicenseService::class)->installationId(),
            'school' => 'Test School',
            'plan' => 'yearly',
            'exp' => now()->addYear()->toDateString(),
            'iat' => now()->toDateString(),
        ], $overrides), $secret ?? $this->secret);
    }

    private function setupAdmin(): User
    {
        $this->post(route('setup.store'), [
            'school_name' => 'Sunrise Public School', 'name' => 'Principal', 'email' => 'p@school.in',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        return User::first();
    }

    public function test_desktop_install_has_no_default_admin_and_requires_setup(): void
    {
        $this->assertSame(0, User::count());
        $this->get('/login')->assertRedirect(route('setup'));
        $this->get('/apply')->assertRedirect(route('setup'));
        $this->get(route('setup'))->assertOk()->assertSee('Continue');

        $admin = $this->setupAdmin();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('Sunrise Public School', Setting::get('school_name'));
        $this->assertMatchesRegularExpression('/^TDS(-[2-9A-HJ-NP-Z]{4}){3}$/', Setting::get('installation_id'));

        // setup cannot be run again
        $this->get(route('setup'))->assertRedirect(route('login'));
    }

    public function test_trial_counts_down_and_then_becomes_read_only(): void
    {
        $admin = $this->setupAdmin();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('30 day(s) left');

        $this->travel(31)->days();
        $status = app(LicenseService::class)->status();
        $this->assertSame('expired', $status['state']);

        // reading still works
        $this->get(route('dashboard'))->assertOk()->assertSee('Read-only mode');
        $this->get(route('students.index'))->assertOk();
        $this->get(route('reports.show', 'summary'))->assertOk();

        // writing is blocked
        $this->post(route('classes.store'), ['name' => 'Class 9'])->assertRedirect(route('license'));
        $this->assertDatabaseMissing('school_classes', ['name' => 'Class 9']);
    }

    public function test_valid_key_activates_and_unlocks_writing(): void
    {
        $admin = $this->setupAdmin();
        $this->travel(40)->days();

        $this->actingAs($admin)->post(route('license.activate'), ['key' => $this->key(['exp' => now()->addYear()->toDateString()])])
            ->assertRedirect(route('license'))->assertSessionHas('success');

        $status = app(LicenseService::class)->status();
        $this->assertSame('licensed', $status['state']);
        $this->assertSame('Test School', $status['school']);

        $this->post(route('classes.store'), ['name' => 'Class 9'])->assertSessionHas('success');
        $this->get(route('license'))->assertOk()->assertSee('Licensed');
    }

    public function test_lifetime_key_never_expires(): void
    {
        $admin = $this->setupAdmin();
        $this->actingAs($admin)->post(route('license.activate'), ['key' => $this->key(['plan' => 'lifetime', 'exp' => null])]);
        $this->travel(5)->years();
        $this->assertSame('licensed', app(LicenseService::class)->status()['state']);
    }

    public function test_rejects_wrong_computer_forged_and_expired_keys(): void
    {
        $admin = $this->setupAdmin();
        $this->actingAs($admin);

        $other = $this->key(['id' => 'TDS-AAAA-BBBB-CCCC']);
        $this->post(route('license.activate'), ['key' => $other])->assertSessionHasErrors('key');

        $forged = $this->key([], base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));
        $this->post(route('license.activate'), ['key' => $forged])->assertSessionHasErrors('key');

        $tampered = preg_replace('/\.([^.]+)\./', '.'.LicenseService::b64e(json_encode(['id' => Setting::get('installation_id'), 'plan' => 'lifetime', 'exp' => null])).'.', $this->key());
        $this->post(route('license.activate'), ['key' => $tampered])->assertSessionHasErrors('key');

        $expired = $this->key(['exp' => now()->subDay()->toDateString()]);
        $this->post(route('license.activate'), ['key' => $expired])->assertSessionHasErrors('key');

        $this->post(route('license.activate'), ['key' => 'garbage'])->assertSessionHasErrors('key');
        $this->assertNull(Setting::get('license_key'));
    }

    public function test_yearly_license_expires_into_read_only(): void
    {
        $admin = $this->setupAdmin();
        $this->actingAs($admin)->post(route('license.activate'), ['key' => $this->key(['exp' => now()->addDays(10)->toDateString()])]);
        $this->get(route('dashboard'))->assertSee('License expires in');

        $this->travel(11)->days();
        $this->assertSame('expired', app(LicenseService::class)->status()['state']);
        $this->post(route('classes.store'), ['name' => 'X'])->assertRedirect(route('license'));
    }

    public function test_turning_the_clock_back_does_not_extend_the_trial(): void
    {
        $admin = $this->setupAdmin();
        $this->travel(31)->days();
        $this->actingAs($admin)->get(route('dashboard')); // records the date it was last seen
        $this->travelBack();

        $this->assertSame('expired', app(LicenseService::class)->status()['state']);
    }

    public function test_registry_first_run_date_wins_over_a_reset_data_folder(): void
    {
        config(['license.first_run' => now()->subDays(29)->toDateString()]);
        $this->setupAdmin();
        $this->assertSame(1, app(LicenseService::class)->status()['days_left']);
    }

    public function test_only_admin_can_activate(): void
    {
        $this->setupAdmin();
        $acct = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $this->actingAs($acct)->post(route('license.activate'), ['key' => $this->key()])->assertForbidden();
        $this->get(route('license'))->assertOk()->assertSee('Ask your admin');
    }

    public function test_paid_activation_mode_blocks_use_until_the_website_issues_a_key(): void
    {
        config(['license.trial_days' => 0, 'license.server' => 'https://tds.example']);

        $this->post(route('setup.store'), [
            'school_name' => 'Sunrise', 'name' => 'P', 'email' => 'p@s.in', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('license'));
        $admin = User::first();

        $status = app(LicenseService::class)->status();
        $this->assertSame(['expired', 'none'], [$status['state'], $status['plan']]);
        $this->actingAs($admin)->get(route('license'))->assertOk()->assertSee('Start your 7-day trial for ₹1')
            ->assertSee('https://tds.example/activate?installation_id='.Setting::get('installation_id'), false);
        $this->get(route('dashboard'))->assertSee('Start trial');
        $this->post(route('classes.store'), ['name' => 'Class 9'])->assertRedirect(route('license'));

        // school pays ₹1 on the website → the website now returns a key → the License screen poll activates it
        $this->serverKey = $this->key(['plan' => '7-day trial', 'exp' => now()->addDays(7)->toDateString()]);
        $this->postJson(route('license.check'))->assertOk()->assertJson(['activated' => true, 'state' => 'licensed']);

        $this->post(route('classes.store'), ['name' => 'Class 9'])->assertSessionHas('success');
    }

    public function test_monthly_renewal_is_picked_up_automatically_and_older_keys_are_ignored(): void
    {
        config(['license.server' => 'https://tds.example']);
        $admin = $this->setupAdmin();
        $this->actingAs($admin)->post(route('license.activate'), ['key' => $this->key(['exp' => now()->addDays(2)->toDateString()])]);

        // website already has next month's key (Autopay charged)
        $next = $this->serverKey = $this->key(['plan' => 'Monthly', 'exp' => now()->addMonth()->toDateString()]);
        $this->travel(61)->minutes(); // past the hourly throttle
        $this->get(route('dashboard'))->assertOk(); // expiring soon → middleware syncs
        $this->assertSame($next, Setting::get('license_key'));

        // an older key from the server never replaces a newer one
        $this->serverKey = $this->key(['exp' => now()->addDays(5)->toDateString()]);
        $this->assertFalse(app(LicenseService::class)->syncOnline(force: true));
        $this->assertSame($next, Setting::get('license_key'));

        // keys for another computer or forged keys are ignored, and being offline is harmless
        $this->serverKey = $this->key(['id' => 'TDS-AAAA-BBBB-CCCC', 'exp' => now()->addYears(9)->toDateString()]);
        $this->assertFalse(app(LicenseService::class)->syncOnline(force: true));
        $this->serverKey = 'offline';
        $this->assertFalse(app(LicenseService::class)->syncOnline(force: true));
        $this->assertSame($next, Setting::get('license_key'));
    }

    public function test_sync_is_throttled(): void
    {
        $this->setupAdmin();
        config(['license.server' => 'https://tds.example']);
        $svc = app(LicenseService::class);
        $svc->syncOnline();
        $svc->syncOnline();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        $svc->syncOnline(force: true);
        \Illuminate\Support\Facades\Http::assertSentCount(2);
    }
}
