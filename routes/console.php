<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PRD 11.4: Retraining model bulanan terjadwal (bukan real-time) lewat antrean Horizon
Schedule::command('forecast:retrain --queue')
    ->monthlyOn(1, '01:00')
    ->name('monthly-forecast-retraining')
    ->withoutOverlapping();

// Fetch dan cache data curah hujan harian dari Open-Meteo Archive
Schedule::command('rainfall:fetch')
    ->dailyAt('02:00')
    ->name('daily-rainfall-sync')
    ->withoutOverlapping();
