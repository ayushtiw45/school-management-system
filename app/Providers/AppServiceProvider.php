<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // the school of the current request, for branding in every view (null on the public site)
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            if (! array_key_exists('brandSchool', $view->getData())) {
                $view->with('brandSchool', \App\Support\Tenant::school());
            }
        });
    }
}
