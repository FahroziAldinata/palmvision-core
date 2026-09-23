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
use App\Http\Controllers\Web\PemanenController;
use App\Http\Controllers\Web\ProduksiHarianController;
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
        Route::post('/', [ProduksiHarianController::class, 'store'])->name('store');

        Route::prefix('validasi')->name('validasi.')->group(function () {
            Route::get('/', [ValidasiProduksiController::class, 'index'])->name('index');
            Route::post('/{produksi}/approve', [ValidasiProduksiController::class, 'approve'])->name('approve');
            Route::post('/{produksi}/koreksi', [ValidasiProduksiController::class, 'koreksi'])->name('koreksi');
        });
    });
});

require __DIR__.'/auth.php';
