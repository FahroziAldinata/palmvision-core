<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\OrganisasiController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health', HealthCheckController::class)->name('health');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GisController;
use App\Http\Controllers\Web\LaporanController;
use App\Http\Controllers\Web\NotificationWebController;
use App\Http\Controllers\Web\PemanenController;
use App\Http\Controllers\Web\PemupukanController;
use App\Http\Controllers\Web\ProduksiHarianController;
use App\Http\Controllers\Web\TaksasiController;
use App\Http\Controllers\Web\ValidasiProduksiController;

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('pemanen', PemanenController::class)->except(['create', 'edit', 'show']);

    Route::prefix('organisasi')->name('organisasi.')->group(function () {
        Route::get('/kebun/{kebun}', [OrganisasiController::class, 'showKebun'])->name('kebun.show');
        Route::get('/afdeling/{afdeling}', [OrganisasiController::class, 'showAfdeling'])->name('afdeling.show');
        Route::get('/blok/{blok}', [OrganisasiController::class, 'showBlok'])->name('blok.show');
    });

    Route::prefix('produksi')->name('produksi.')->group(function () {
        Route::get('/', [ProduksiHarianController::class, 'index'])->name('index');
        Route::get('/create', [ProduksiHarianController::class, 'create'])->name('create');
        Route::get('/check-existing', [ProduksiHarianController::class, 'checkExisting'])->name('check-existing');
        Route::post('/', [ProduksiHarianController::class, 'store'])->name('store');

        Route::prefix('validasi')->name('validasi.')->group(function () {
            Route::get('/', [ValidasiProduksiController::class, 'index'])->name('index');
            Route::post('/{produksi}/approve', [ValidasiProduksiController::class, 'approve'])->name('approve');
            Route::post('/{produksi}/koreksi', [ValidasiProduksiController::class, 'koreksi'])->name('koreksi');
        });
    });

    Route::prefix('taksasi')->name('taksasi.')->group(function () {
        Route::get('/', [TaksasiController::class, 'index'])->name('index');
        Route::get('/create', [TaksasiController::class, 'create'])->name('create');
        Route::post('/', [TaksasiController::class, 'store'])->name('store');
        Route::get('/akurasi', [TaksasiController::class, 'akurasi'])->name('akurasi');
    });

    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/', [LaporanController::class, 'index'])->name('index');
        Route::get('/pdf', [LaporanController::class, 'downloadPdf'])->name('pdf');
        Route::get('/excel', [LaporanController::class, 'downloadExcel'])->name('excel');
    });

    Route::prefix('gis')->name('gis.')->group(function () {
        Route::get('/peta', [GisController::class, 'index'])->name('index');
        Route::get('/tiles/{z}/{x}/{y}.pbf', [GisController::class, 'tiles'])->name('tiles');
        Route::get('/bloks/{blok}', [GisController::class, 'blockDetail'])->name('blok.detail');
        Route::post('/bloks/{blok}/import', [GisController::class, 'importPoligon'])->name('blok.import');
    });

    // Phase 5 Modul 4: Pemupukan Minimal Log
    Route::prefix('pemupukan')->name('pemupukan.')->group(function () {
        Route::get('/', [PemupukanController::class, 'index'])->name('index');
        Route::post('/', [PemupukanController::class, 'store'])->name('store');
    });

    // Phase 5 Modul 2: In-App Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', [NotificationWebController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [NotificationWebController::class, 'markAllRead'])->name('mark_all_read');
    });
});

require __DIR__.'/auth.php';
