<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to one or more roles: `role:coop_admin,technician`.
 *
 * A role mismatch is a plain 403 — the 404 rule of SPEC section 7.2
 * only applies to resources of another cooperative, which the global
 * scope already hides from route model binding.
 */
class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && in_array($user->role->value, $roles, true),
            403,
        );

        return $next($request);
    }
}
