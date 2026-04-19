<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'id',
        'name',
        'email',
        'user',
        'password', // AES-256-CBC encrypted base64
        'roles',
    ];

    protected $hidden = [
        'password',
    ];

    // ── NO 'array' cast for roles ─────────────────────────────────────────────
    // MongoDB already returns arrays as native PHP arrays.
    // Laravel's 'array' cast calls json_decode() which fails on a non-string.
    // We normalise to array in the accessor below instead.

    /**
     * Always return roles as a plain PHP array regardless of how MongoDB
     * stored it (array, single string, or missing).
     */
    public function getRolesAttribute(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return [$value];
        }
        return [];
    }
}
