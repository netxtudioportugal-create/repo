<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Nobody practices before acknowledging what the app is and is not. */
class EnsureAcknowledged
{
    public function handle(Request $request, Closure $next): Response
    {
        $learner = $request->attributes->get('learner');

        if (! $learner?->acknowledged_at) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
