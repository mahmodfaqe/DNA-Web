<?php

namespace App\Providers;

use App\Services\DnaBackendClient;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DnaBackendClient::class, fn () => DnaBackendClient::fromConfig());
    }

    public function boot(): void
    {
        // Only known language codes may occupy the first URL segment, so
        // /result/... can never be mistaken for a locale.
        Route::pattern('locale', implode('|', Locales::codes()));

        // Behind a TLS-terminating proxy Laravel would otherwise emit http://
        // links on an https:// page and trip mixed-content blocking.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
