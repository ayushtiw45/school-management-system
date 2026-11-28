<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the logged-in user's school as the current tenant. Runs before route-model binding
 * (see bootstrap/app.php), so /students/{id} of another school is simply "not found".
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // always read the school fresh: its plan status may have changed (payment, webhook, owner action)
        Tenant::set($user?->school_id ? \App\Models\School::find($user->school_id) : null);

        return $next($request);
    }
}
