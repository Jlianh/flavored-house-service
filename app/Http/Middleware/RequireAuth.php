<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the JWT sent in cookies or the Authorization: Bearer header.
 * Attaches the decoded payload to $request->user_payload.
 *
 * Mirrors the requireAuth middleware from the original Node.js auth.js.
 */
class RequireAuth
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('token');

        if (!$token) {
            $header = $request->header('Authorization', '');
            if (!str_starts_with($header, 'Bearer ')) {
                return response()->json(['error' => 'Authorization missing or malformed'], 401);
            }
            $token = substr($header, 7);
        }

        try {
            $payload = $this->jwt->verify($token);
            $request->merge(['_jwt_payload' => $payload]);
        } catch (Exception) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        return $next($request);
    }
}
