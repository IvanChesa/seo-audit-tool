<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conservative headers for every response. API responses are never meant to
 * be rendered, framed or sniffed as another content type by a browser; the SPA
 * shell may only load its own scripts, styles and API.
 */
class SecurityHeaders
{
    private const API_POLICY = "default-src 'none'; frame-ancestors 'none'";

    private const SPA_POLICY = "default-src 'self'; img-src 'self' data:; object-src 'none'; "
        ."base-uri 'none'; form-action 'self'; frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        if (! $request->routeIs('spa')) {
            $response->headers->set('Content-Security-Policy', self::API_POLICY);
        } elseif (! Vite::isRunningHot()) {
            // Under `npm run dev` scripts, styles and hot reloading come from the
            // Vite dev server with inline preambles, so the policy covers builds only.
            $response->headers->set('Content-Security-Policy', self::SPA_POLICY);
        }

        return $response;
    }
}
