<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);
        $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class]);
        $middleware->validateCsrfTokens(except: ['razorpay/webhook']);
        $middleware->encryptCookies(except: ['locale']);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/account');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();

// Hostinger layout: app in  domains/<site>/tds-portal,  web root in  domains/<site>/public_html
if (is_dir($publicHtml = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public_html') && is_file($publicHtml.DIRECTORY_SEPARATOR.'index.php')
    && str_contains((string) file_get_contents($publicHtml.DIRECTORY_SEPARATOR.'index.php'), 'tds-portal')) {
    $app->usePublicPath($publicHtml);
}

return $app;
