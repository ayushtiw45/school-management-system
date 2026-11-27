<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trial over / license expired → read-only: viewing, printing and reports still work,
 * but nothing can be added or changed until a license key is entered.
 */
class EnforceLicense
{
    private const ALWAYS_ALLOWED = ['license', 'license.activate', 'login', 'logout', 'setup', 'setup.store', 'password.update'];

    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->license->enabled()) {
            return $next($request);
        }

        $status = $this->license->status();
        View::share('license', $status);

        if ($status['state'] === 'expired' && ! $request->isMethodSafe() && ! $request->routeIs(...self::ALWAYS_ALLOWED)) {
            return redirect()->route('license')
                ->with('error', 'Your '.($status['plan'] === 'trial' ? 'free trial has' : 'license has').' ended. You can still view and print everything, but to add or change records please activate a license.');
        }

        return $next($request);
    }
}
