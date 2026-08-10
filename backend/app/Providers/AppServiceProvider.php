<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Edge nginx rewrites /laravel/* → /*; prefer configured APP_URL for absolute links.
        $root = config('app.url');
        if (is_string($root) && $root !== '') {
            URL::forceRootUrl($root);
        }
    }
}
