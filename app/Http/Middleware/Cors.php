<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS middleware.
 * Mirrors the allowed origins from the original Node.js app.js.
 */
class Cors
{
    private array $allowedOrigins = [
        'http://localhost:4200',
        'https://www.lacasitadelsabor.com',
        'https://lacasitadelsabor.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin', '');

        $response = $next($request);

        if (in_array($origin, $this->allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        // Handle pre-flight
        if ($request->isMethod('OPTIONS')) {
            $response->setStatusCode(204);
        }

        return $response;
    }
}
