<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LicenseIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/** Second half of public/install.php: create tables and the admin account, then lock the installer. */
class InstallController extends Controller
{
    public function finish(Request $request, LicenseIssuer $issuer)
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
            'phone' => $admin['phone'] ?: '-',
            'school_name' => 'Administrator',
            'password' => $admin['password_hash'],
            'is_admin' => true,
        ])->save();

        $issuer->keys(); // create the license signing key
        $disk->delete('install-admin.json');
        $disk->put('installed.flag', now()->toIso8601String());
        @unlink(public_path('install.php'));

        return redirect()->route('login')->with('success', 'Installation complete. Log in with your admin email and password.');
    }
}
