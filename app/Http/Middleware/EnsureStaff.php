<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('staff.login'));
        }

        // "staff" alone: any staff account, read-only included.
        // "staff:full": staff who may act (everyone except read-only).
        // "staff:<role>": that role or admin.
        if ($role === 'full') {
            abort_if($user->isReadOnly(), 403, 'Read-only access.');
        } elseif ($role !== null) {
            abort_unless($user->hasRole($role), 403);
        }

        return $next($request);
    }
}
