<?php

    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\AuthController;

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });
?>