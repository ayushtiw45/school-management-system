<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetTenant::class,
            \App\Http\Middleware\SetLocale::class,
        ]);
        // The tenant must be known before {student}, {payment}… are looked up
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetTenant::class,
        );
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'school' => \App\Http\Middleware\EnsureSchoolAccess::class,
            'super' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
        $middleware->validateCsrfTokens(except: ['razorpay/webhook']);
        $middleware->encryptCookies(except: ['locale']);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn (Request $r) => $r->user()?->is_super_admin && ! $r->user()->school_id ? '/super' : '/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
