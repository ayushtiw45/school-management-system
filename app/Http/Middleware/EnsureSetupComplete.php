<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Fresh install with no users: send everyone to the first-run setup screen. */
class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $needsSetup = ! User::query()->exists();

        if ($needsSetup && ! $request->routeIs('setup', 'setup.store')) {
            return redirect()->route('setup');
        }
        if (! $needsSetup && $request->routeIs('setup', 'setup.store')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
