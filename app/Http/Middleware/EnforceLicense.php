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
    private const ALWAYS_ALLOWED = ['license', 'license.activate', 'license.check', 'login', 'logout', 'setup', 'setup.store', 'password.update'];

    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->license->enabled()) {
            return $next($request);
        }

        $status = $this->license->status();
        // Pick up renewals / activation from the website (hourly; every 10 minutes when about to lock)
        if ($status['state'] !== 'licensed' || ($status['days_left'] !== null && $status['days_left'] <= 3)) {
            if ($this->license->syncOnline(minutes: $status['state'] === 'licensed' ? 60 : 10)) {
                $status = $this->license->status();
            }
        }
        View::share('license', $status);

        if ($status['state'] === 'expired' && ! $request->isMethodSafe() && ! $request->routeIs(...self::ALWAYS_ALLOWED)) {
            return redirect()->route('license')->with('error', $status['plan'] === 'none'
                ? 'Please activate the software first: start your trial from this screen.'
                : 'Your '.($status['plan'] === 'trial' ? 'free trial has' : 'license has').' ended. You can still view and print everything, but to add or change records please renew.');
        }

        return $next($request);
    }
}
