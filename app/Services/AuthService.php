<?php

namespace App\Services;

use Exception;

/**
 * AES-256-CBC password encryption/decryption.
 *
 * Mirrors the original Node.js authService.js exactly.
 *
 * Required env vars:
 *   AES_SECRET_KEY  — 64-character hex string  (32 bytes)
 *   AES_SECRET_IV   — 32-character hex string  (16 bytes)
 */
class AuthService
{
    private function getKeyAndIV(): array
    {
        $key = env('AES_SECRET_KEY');
        $iv  = env('AES_SECRET_IV');

        if (!$key || !$iv) {
            throw new Exception('AES_SECRET_KEY and AES_SECRET_IV must be set in environment variables');
        }
        if (strlen($key) !== 64) {
            throw new Exception('AES_SECRET_KEY must be a 64-character hex string (32 bytes)');
        }
        if (strlen($iv) !== 32) {
            throw new Exception('AES_SECRET_IV must be a 32-character hex string (16 bytes)');
        }

        return [
            'key' => hex2bin($key),
            'iv'  => hex2bin($iv),
        ];
    }

    /**
     * Decrypt an AES-256-CBC base64-encoded ciphertext.
     *
     * @param  string $encryptedBase64  — password as stored in MongoDB
     * @return string                  — plain-text password
     */
    public function decryptPassword(string $encryptedBase64): string
    {
        ['key' => $key, 'iv' => $iv] = $this->getKeyAndIV();

        $ciphertext = base64_decode($encryptedBase64);
        $decrypted  = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new Exception('Failed to decrypt password');
        }

        return $decrypted;
    }

    /**
     * Encrypt a plain-text password using AES-256-CBC.
     *
     * @param  string $plainText
     * @return string  — base64-encoded ciphertext (ready to store in MongoDB)
     */
    public function encryptPassword(string $plainText): string
    {
        ['key' => $key, 'iv' => $iv] = $this->getKeyAndIV();

        $encrypted = openssl_encrypt(
            $plainText,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new Exception('Failed to encrypt password');
        }

        return base64_encode($encrypted);
    }

    /**
     * Verify a plain-text password against the encrypted one stored in MongoDB.
     */
    public function verifyPassword(string $plainText, string $encryptedBase64): bool
    {
        return $this->decryptPassword($encryptedBase64) === $plainText;
    }
}
