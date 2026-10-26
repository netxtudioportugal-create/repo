<?php

namespace App\Http\Middleware;

use App\Models\Learner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Loads the pseudonymous learner from the encrypted cookie, if there is one. */
class ResolveLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->cookie(config('examprep.learner_cookie'));
        $learner = is_string($id) ? Learner::find($id) : null;

        if ($learner && (! $learner->last_seen_at || $learner->last_seen_at->lt(now()->subHour()))) {
            $learner->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('learner', $learner);

        return $next($request);
    }
}
