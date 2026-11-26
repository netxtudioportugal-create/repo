<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A read-only staff account must not create data anywhere, including the
 * candidate side (learner records, practice sessions, logged responses).
 * Screens are available to it through /staff/items/{item}/preview instead.
 */
class BlockReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->isReadOnly(), 403, 'Read-only access: use the item preview in the staff area.');

        return $next($request);
    }
}
