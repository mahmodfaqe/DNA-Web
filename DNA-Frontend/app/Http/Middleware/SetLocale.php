<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! Locales::supports($locale)) {
            return redirect()->to(
                $this->swapFirstSegment($request, Locales::preferred($request))
            );
        }

        app()->setLocale($locale);

        // Laravel points Carbon at the app locale, but Carbon's `ku` is
        // Kurmanji written in Latin script — a Sorani page came back reading
        // "berî 0 saniye". Central Kurdish is `ckb`, which is exactly what this
        // locale's tag already holds; `ar` and `en` map to themselves.
        Carbon::setLocale(Locales::tag($locale));

        $request->session()->put('locale', $locale);

        // So every route() call inherits the current language without each
        // caller having to remember to pass it.
        URL::defaults(['locale' => $locale]);

        // ڕێگە دەگرێت لەوەی locale وەک پارامێتەری یەکەم بنێردرێت بۆ فەنکشنەکانی Controller
        $request->route()->forgetParameter('locale');

        $response = $next($request);
        $response->headers->set('Content-Language', Locales::tag($locale));

        return $response;
    }

    /**
     * Rewrite /xx/rest-of-path to /{locale}/rest-of-path, preserving the query
     * string so a shared link never loses its parameters on redirect.
     */
    private function swapFirstSegment(Request $request, string $locale): string
    {
        $segments = $request->segments();
        $segments[0] = $locale;

        $path = implode('/', $segments);
        $query = $request->getQueryString();

        return url($path) . ($query ? '?' . $query : '');
    }
}
