<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PksWebhookController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

// Public Webhook from PKS / weighbridge (authenticated via HMAC SHA-256 + X-API-Key)
Route::post('/webhooks/pks/rendemen', [PksWebhookController::class, 'handle'])->name('api.webhooks.pks.rendemen');

Route::prefix('v1')->group(function () {
    // Auth routes for mobile worker
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

    // Webhook alias under v1
    Route::post('/webhooks/pks/rendemen', [PksWebhookController::class, 'handle'])->name('api.v1.webhooks.pks.rendemen');

    // Authenticated API routes (Sanctum / Web session)
    Route::middleware(['auth:sanctum,web'])->group(function () {
        // Auth profile
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        // Forecasting (Phase 4)
        Route::post('/forecast/generate', [ForecastController::class, 'generate'])->name('api.forecast.generate');
        Route::get('/forecast/{blok_id}', [ForecastController::class, 'show'])->name('api.forecast.show');

        // Mobile Master Data Bootstrap & Offline Sync (Phase 5)
        Route::get('/mobile/bootstrap', [SyncController::class, 'bootstrap'])->name('api.mobile.bootstrap');
        Route::post('/produksi/sync', [SyncController::class, 'syncProduksi'])->name('api.produksi.sync');
        Route::post('/taksasi/sync', [SyncController::class, 'syncTaksasi'])->name('api.taksasi.sync');

        // Notifications (Phase 5 UC-13)
        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('api.notifications.mark_all_read');
    });
});
