<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin, 403);

        // New code uploaded by hand (not through Software Update)? Bring the database up to date once.
        $version = (string) config('app.version');
        if (PlatformSetting::get('migrated_version') !== $version) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                PlatformSetting::put('migrated_version', $version);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
