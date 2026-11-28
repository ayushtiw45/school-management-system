<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/** Second half of public/install.php: create the tables and the platform owner's login, then lock the installer. */
class InstallController extends Controller
{
    public function finish(Request $request)
    {
        $disk = Storage::disk('local');
        abort_if($disk->exists('installed.flag'), 404);
        abort_unless($disk->exists('install-admin.json') && hash_equals((string) env('INSTALL_TOKEN'), (string) $request->query('token')), 403);

        Artisan::call('migrate', ['--force' => true]);

        $admin = json_decode($disk->get('install-admin.json'), true);
        // password_hash is already a bcrypt hash; the "hashed" cast keeps hashed values as they are
        (new User)->forceFill([
            'name' => $admin['name'],
            'email' => $admin['email'],
            'phone' => $admin['phone'] ?: null,
            'role' => 'admin',
            'is_active' => true,
            'is_super_admin' => true,
            'password' => $admin['password_hash'],
        ])->save();
        if (! empty($admin['phone'])) {
            PlatformSetting::put('contact_phone', $admin['phone']);
        }

        $disk->delete('install-admin.json');
        $disk->put('installed.flag', now()->toIso8601String());
        @unlink(public_path('install.php'));

        return redirect()->route('login')->with('success', 'Installation complete. Log in with your email and password to open the owner panel.');
    }
}
