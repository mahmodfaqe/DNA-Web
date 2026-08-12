<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening.
 *
 * The Content-Security-Policy is deliberately strict: because every asset and
 * font is now bundled and self-hosted, `self` is genuinely sufficient. If
 * someone later reaches for a CDN script tag, the page will break loudly in
 * development rather than quietly adding a third-party dependency to production.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            // Inline styles are still needed for the server-rendered SVG tracks,
            // where each bar segment carries computed widths.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            // default-src does not cover these two. Without them a plugin or a
            // Worker is governed by nothing at all.
            "object-src 'none'",
            "worker-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $policy);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Only when the request already arrived over TLS. Sending HSTS over
        // plain HTTP is ignored by browsers by design, and sending it from a
        // local http://localhost:8080 would pin a developer's own machine to
        // HTTPS for a year. `trustProxies` makes this true behind the reverse
        // proxy that terminates TLS in production.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
