<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * School software access:
 *  locked  (₹1 trial not paid yet) → every screen locked, only Billing opens
 *  active  → everything works
 *  expired → read-only: viewing, printing and reports work, nothing can be changed
 *  blocked → logged out
 */
class EnsureSchoolAccess
{
    private const ALWAYS = ['billing', 'billing.*', 'logout', 'password.edit', 'password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user->is_super_admin && ! $user->school_id) {
            return redirect()->route('super.dashboard');
        }

        $school = Tenant::school();
        abort_unless($school, 403);
        $state = $school->accessState();
        View::share('access', ['state' => $state, 'days' => $school->daysLeft(), 'school' => $school]);

        if ($state === 'blocked') {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'This school account is blocked. Please contact support.']);
        }
        if ($request->routeIs(...self::ALWAYS)) {
            return $next($request);
        }
        if ($state === 'locked') {
            return redirect()->route('billing')->with('error', 'Start your 7-day trial for ₹1 to unlock all features.');
        }
        if ($state === 'expired' && ! $request->isMethodSafe()) {
            return redirect()->route('billing')->with('error', 'Your plan has ended. You can still view and print everything; renew to add or change records.');
        }

        return $next($request);
    }
}
