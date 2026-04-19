<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to users that possess at least one of the given roles.
 * Must be applied AFTER RequireAuth.
 *
 * Usage (in routes/api.php):
 *   ->middleware(['auth.jwt', 'role:administrador'])
 *
 * Mirrors requireRole() from the original Node.js auth.js.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $payload = $request->get('_jwt_payload');

        if (!$payload) {
            return response()->json(['error' => 'Access denied. Required role: ' . implode(' or ', $roles)], 403);
        }

        // Support both 'roles' (array) and 'role' (scalar or array) in token payload
        $userRoles = (array) ($payload['roles'] ?? $payload['role'] ?? []);

        $hasRole = count(array_intersect($roles, $userRoles)) > 0;

        if (!$hasRole) {
            return response()->json(['error' => 'Access denied. Required role: ' . implode(' or ', $roles)], 403);
        }

        return $next($request);
    }
}
