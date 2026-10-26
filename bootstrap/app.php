<?php

use App\Http\Middleware\EdgeHeaders;
use App\Http\Middleware\EnsureAcknowledged;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\ResolveLearner;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TLS ends at a proxy (Laravel Cloud's edge, Cloudflare, a load balancer).
        // Trusting every proxy is safe only when the origin cannot be reached
        // directly; see docs/CLOUDFLARE.md (Cloudflare Tunnel or origin firewall).
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            ResolveLearner::class,
            SetLocale::class,
            EdgeHeaders::class,
        ]);

        $middleware->alias([
            'acknowledged' => EnsureAcknowledged::class,
            'staff' => EnsureStaff::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('staff.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
