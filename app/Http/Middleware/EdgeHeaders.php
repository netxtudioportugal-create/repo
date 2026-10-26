<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every dynamic page here is personal (answers, scores, staff data), so no
 * proxy or CDN may store it. Cloudflare honours Cache-Control and the
 * CDN-specific header below; static files are cached through public/.htaccess.
 */
class EdgeHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
