<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Thin wrapper around firebase/php-jwt.
 * Mirrors the JWT behaviour from the original Node.js auth.js.
 */
class JwtService
{
    private string $secret;
    private int    $ttlSeconds;

    public function __construct()
    {
        $this->secret = env('JWT_SECRET') ?? throw new Exception('JWT_SECRET not set');

        // Parse "8h", "30m", "3600" etc. into seconds
        $raw = env('JWT_EXPIRES_IN', '8h');
        $this->ttlSeconds = $this->parseExpiry($raw);
    }

    /** Build and sign a JWT for the given user payload. */
    public function sign(array $payload): string
    {
        $now = time();
        $claims = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ]);

        return JWT::encode($claims, $this->secret, 'HS256');
    }

    /**
     * Verify and decode a token.
     *
     * @throws \Firebase\JWT\ExpiredException
     * @throws \Firebase\JWT\SignatureInvalidException
     */
    public function verify(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        return (array) $decoded;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function parseExpiry(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $unit  = strtolower(substr($value, -1));
        $amount = (int) substr($value, 0, -1);

        return match ($unit) {
            's' => $amount,
            'm' => $amount * 60,
            'h' => $amount * 3600,
            'd' => $amount * 86400,
            default => 8 * 3600, // default 8 h
        };
    }

    public function getTtlMs(): int
    {
        return $this->ttlSeconds * 1000;
    }
}
