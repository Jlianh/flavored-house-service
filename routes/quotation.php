<?php

    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\QuotationController;

    Route::prefix('quotation')->group(function () {
        Route::get('/', [QuotationController::class, 'index']);
        Route::post('/', [QuotationController::class, 'store']);
    });
?>