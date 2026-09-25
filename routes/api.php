<?php

use App\Http\Controllers\Api\ForecastController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum,web'])->group(function () {
    Route::post('/forecast/generate', [ForecastController::class, 'generate'])->name('api.forecast.generate');
    Route::get('/forecast/{blok_id}', [ForecastController::class, 'show'])->name('api.forecast.show');
});
