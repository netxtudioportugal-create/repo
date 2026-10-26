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

        abort_unless($user->hasRole($role), 403);

        return $next($request);
    }
}
