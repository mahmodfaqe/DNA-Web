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
        // Only a language-shaped segment may occupy the first URL segment, so
        // /result/... can never be mistaken for a locale. Unsupported codes are
        // deliberately allowed to match: SetLocale redirects /fr/result/x to the
        // reader's own language instead of letting it 404, which it cannot do
        // for a segment the router refuses to route in the first place.
        Route::pattern('locale', '[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?');

        // The shared layout calls route('analysis.index'), and every route in
        // this app needs a {locale}. A request that never reaches SetLocale —
        // an unrouteable path, or a model binding that fails before it — would
        // otherwise turn its own error page into a 500.
        URL::defaults(['locale' => Locales::FALLBACK]);

        // Behind a TLS-terminating proxy Laravel would otherwise emit http://
        // links on an https:// page and trip mixed-content blocking.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
