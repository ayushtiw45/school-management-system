<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive.']);
        }

        if ($roles) {
            $allowed = false;
            foreach ($roles as $role) {
                [$name, $mode] = array_pad(explode(':', $role, 2), 2, null);
                if ($user->role === $name && ($mode !== 'read' || $request->isMethodSafe())) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                $readOnly = collect($roles)->contains(fn ($r) => $r === $user->role.':read');
                abort(403, $readOnly ? 'You can view this page but cannot change it.' : 'You do not have permission to open this page.');
            }
        }

        return $next($request);
    }
}
