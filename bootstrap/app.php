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
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
        $middleware->web(append: [
            \App\Http\Middleware\EnsureSetupComplete::class,
            \App\Http\Middleware\EnforceLicense::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Desktop install: keep uploads, sessions, cache and logs in the user's data folder
// so reinstalling or updating the program never touches the school's data.
if ($dataDir = getenv('TDS_DATA_DIR') ?: ($_SERVER['TDS_DATA_DIR'] ?? null)) {
    $app->useStoragePath($dataDir.DIRECTORY_SEPARATOR.'storage');
}

return $app;
