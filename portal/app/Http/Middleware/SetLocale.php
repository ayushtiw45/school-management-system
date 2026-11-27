<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['en' => 'English', 'hi' => 'हिन्दी'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale') ?? $request->user()?->locale ?? $request->cookie('locale');
        if (! isset(self::LOCALES[$locale])) {
            $locale = str_starts_with((string) $request->getPreferredLanguage(['en', 'hi']), 'hi') ? 'hi' : 'en';
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
