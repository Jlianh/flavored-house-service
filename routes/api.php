<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Mirrors the original Node.js Express routing:
|   /api/auth/*       → AuthController
|   /api/quotation/*  → QuotationController
|
*/

// ── Health check ──────────────────────────────────────────────────────────────
Route::get('/', fn () => response()->json([
    'status'  => 'ok',
    'message' => 'Spice Quotation API running',
]));

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {

    // Public
    Route::post('/login',             [AuthController::class, 'login']);
    Route::post('/logout',            [AuthController::class, 'logout']);
    Route::post('/sendRestoreEmail',  [AuthController::class, 'sendRestoreEmail']);
    Route::post('/restore',           [AuthController::class, 'restorePassword']);
    Route::get('/users/{id}',         [AuthController::class, 'getUserById']);

    // Requires valid JWT
    Route::middleware('auth.jwt')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        // Requires admin role
        Route::middleware('role:administrador')->group(function () {
            Route::post('/users',         [AuthController::class, 'createUser']);
            Route::delete('/users/{id}',  [AuthController::class, 'deleteUser']);
            Route::get('/users',          [AuthController::class, 'listUsers']);
        });
    });
});

// ── Quotation ─────────────────────────────────────────────────────────────────
Route::prefix('quotation')->group(function () {
    Route::post('/',             [QuotationController::class, 'create']);
    Route::post('/preview',      [QuotationController::class, 'preview']);
    Route::post('/bill',         [QuotationController::class, 'createBill']);
    Route::post('/bill/preview', [QuotationController::class, 'previewBill']);
    Route::get('/debug-image',   [QuotationController::class, 'debugImage']);
});
