<?php

namespace App\Jobs;

use App\Domain\Organisasi\Models\Blok;
use App\Services\ForecastingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateForecastJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $blokId,
        public int $horizonMonths = 3
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ForecastingService $forecastingService): void
    {
        $blok = Blok::find($this->blokId);

        if (! $blok) {
            Log::warning("GenerateForecastJob: Blok dengan ID {$this->blokId} tidak ditemukan.");

            return;
        }

        Log::info("Memulai proses background forecast untuk Blok {$blok->kode_blok} ({$this->blokId})");
        $results = $forecastingService->generateAndStoreForecast($blok, $this->horizonMonths);
        Log::info("Selesai menghasilkan forecast untuk Blok {$blok->kode_blok}, tersimpan {$results->count()} periode.");
    }
}
