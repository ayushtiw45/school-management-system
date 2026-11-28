<?php

namespace Tests\Feature;

use App\Models\UpdateLog;
use App\Models\User;
use App\Services\Updater;
use App\Support\Tenant;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class UpdaterTest extends SchoolTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        // a fake installed app: version 1.0.0 with a .env and school uploads that must survive updates
        $this->root = sys_get_temp_dir().'/tds-upd-'.uniqid();
        $this->write('VERSION', "1.0.0\n");
        $this->write('composer.lock', file_get_contents(base_path('composer.lock')));
        $this->write('routes/web.php', '<?php // old routes');
        $this->write('app/Providers/AppServiceProvider.php', '<?php // old provider');
        $this->write('resources/views/old.blade.php', 'old view');
        $this->write('.env', "DB_PASSWORD=secret\n");
        $this->write('storage/app/private/students/photo.jpg', 'PHOTO');
        $this->app->instance(Updater::class, new Updater($this->root));
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        foreach (glob(Updater::backupDir().'/*') ?: [] as $f) {
            @unlink($f);
        }
        @unlink(storage_path('app/updates/pending.zip'));
        parent::tearDown();
    }

    private function write(string $rel, string $content): void
    {
        @mkdir(dirname($this->root.'/'.$rel), 0777, true);
        file_put_contents($this->root.'/'.$rel, $content);
    }

    /** @param array<string,string> $files */
    private function package(array $files = [], array $manifest = []): string
    {
        $path = sys_get_temp_dir().'/pkg-'.uniqid().'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('update.json', json_encode($manifest + [
            'app' => 'tds-school', 'version' => '1.2.0', 'built_at' => now()->toIso8601String(), 'php' => '8.3.0',
            'vendor' => false, 'composer_lock' => sha1_file(base_path('composer.lock')), 'notes' => ['New reports'],
        ]));
        foreach ($files + [
            'VERSION' => "1.2.0\n",
            'routes/web.php' => '<?php // new routes',
            'app/Providers/AppServiceProvider.php' => '<?php // new provider',
            'app/Services/NewThing.php' => '<?php // brand new file',
        ] as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function owner(): User
    {
        Tenant::set(null);

        return User::where('email', 'owner@tds.test')->first();
    }

    public function test_update_replaces_code_but_never_env_or_uploads_and_can_be_undone(): void
    {
        $pkg = $this->package([
            '.env' => 'DB_PASSWORD=hacked',                       // must be ignored
            'storage/app/private/students/photo.jpg' => 'WIPED',  // must be ignored
            'public/install.php' => '<?php // installer',         // must be ignored
        ]);
        $updater = app(Updater::class);

        $log = $updater->apply($pkg);

        $this->assertSame('success', $log->status);
        $this->assertSame(['1.0.0', '1.2.0'], [$log->from_version, $log->to_version]);
        $this->assertSame('1.2.0', $updater->currentVersion());
        $this->assertSame('<?php // new routes', file_get_contents($this->root.'/routes/web.php'));
        $this->assertFileExists($this->root.'/app/Services/NewThing.php');
        $this->assertSame("DB_PASSWORD=secret\n", file_get_contents($this->root.'/.env'));
        $this->assertSame('PHOTO', file_get_contents($this->root.'/storage/app/private/students/photo.jpg'));
        $this->assertFileDoesNotExist($this->root.'/public/install.php');
        $this->assertFileExists(Updater::backupDir().'/'.$log->code_backup);
        $this->assertFalse(app()->isDownForMaintenance(), 'site must be back online');

        // undo → old code again
        $updater->rollback($log);
        $this->assertSame('1.0.0', $updater->currentVersion());
        $this->assertSame('<?php // old routes', file_get_contents($this->root.'/routes/web.php'));
        $this->assertSame("DB_PASSWORD=secret\n", file_get_contents($this->root.'/.env'));
    }

    public function test_rejects_wrong_or_unsafe_packages(): void
    {
        $updater = app(Updater::class);
        $cases = [
            'not a TDS School update' => $this->package([], ['app' => 'something-else']),
            'unsafe path' => $this->package(['../../evil.php' => 'x']),
            'full package' => $this->package([], ['composer_lock' => 'different']),
            'PHP 99' => $this->package([], ['php' => '99.0.0']),
        ];
        foreach ($cases as $expected => $pkg) {
            try {
                $updater->inspect($pkg);
                $this->fail("should reject: $expected");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }
        $bad = sys_get_temp_dir().'/notzip-'.uniqid().'.zip';
        file_put_contents($bad, 'hello');
        $this->expectExceptionMessage('not a valid zip');
        $updater->inspect($bad);
    }

    public function test_owner_panel_upload_check_and_update(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->get('/super/update')->assertOk()->assertSee('1.0.0');

        $file = new UploadedFile($this->package(), 'tds-update-1.2.0.zip', 'application/zip', null, true);
        $this->post('/super/update/upload', ['package' => $file, 'password' => 'wrong'])->assertSessionHasErrors('password');

        $file = new UploadedFile($this->package(), 'tds-update-1.2.0.zip', 'application/zip', null, true);
        $this->post('/super/update/upload', ['package' => $file, 'password' => 'password'])->assertRedirect('/super/update');
        $this->get('/super/update')->assertSee('Ready to update: 1.0.0 → 1.2.0')->assertSee('New reports');
        $this->assertFileExists($this->root.'/routes/web.php');
        $this->assertSame('1.0.0', app(Updater::class)->currentVersion(), 'nothing changes before confirming');

        $this->post('/super/update/apply')->assertRedirect('/super/update')->assertSessionHas('success');
        $this->assertSame('1.2.0', app(Updater::class)->currentVersion());
        $this->get('/super/update')->assertSee('1.2.0')->assertSee('Undo');

        $log = UpdateLog::first();
        $this->get(route('super.backups.download', $log->code_backup))->assertOk();
        $this->get('/super/backups/..%2F.env')->assertNotFound();

        // a bad file is rejected with a message and nothing is pending
        $bad = new UploadedFile($this->package([], ['app' => 'x']), 'x.zip', 'application/zip', null, true);
        $this->post('/super/update/upload', ['package' => $bad, 'password' => 'password'])->assertSessionHas('error');
        $this->assertFileDoesNotExist(storage_path('app/updates/pending.zip'));
    }

    public function test_school_admins_cannot_update(): void
    {
        $this->actingAs($this->user())->get('/super/update')->assertForbidden();
        $this->post('/super/update/apply')->assertForbidden();
    }
}
