<?php

namespace App\Console\Commands;

use App\Domain\Organisasi\Models\Blok;
use App\Jobs\GenerateForecastJob;
use App\Services\ForecastingService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RetrainForecastCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forecast:retrain
                            {--kebun= : ID atau Kode Kebun untuk membatasi proses}
                            {--blok= : ID atau Kode Blok untuk membatasi proses}
                            {--queue : Jalankan asynchronously lewat antrean worker Horizon}
                            {--horizon=3 : Jumlah horizon proyeksi dalam bulan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan retraining model time-series Prophet bulanan dan menyimpan hasil proyeksi produksi';

    /**
     * Execute the console command.
     */
    public function handle(ForecastingService $forecastingService): int
    {
        $kebunOpt = $this->option('kebun');
        $blokOpt = $this->option('blok');
        $isQueue = (bool) $this->option('queue');
        $horizonMonths = (int) $this->option('horizon');

        $this->info('======================================================');
        $this->info('PALMVISION — Retraining Bulanan Model Time-Series');
        $this->info('======================================================');

        $query = Blok::query()->with('afdeling.kebun');

        if ($blokOpt) {
            $query->where(function ($q) use ($blokOpt) {
                if (Str::isUuid((string) $blokOpt)) {
                    $q->where('id', $blokOpt);
                } else {
                    $q->where('kode_blok', $blokOpt);
                }
            });
        } elseif ($kebunOpt) {
            $query->whereHas('afdeling.kebun', function ($q) use ($kebunOpt) {
                if (Str::isUuid((string) $kebunOpt)) {
                    $q->where('id', $kebunOpt);
                } else {
                    $q->where('kode_kebun', $kebunOpt);
                }
            });
        }

        $bloks = $query->orderBy('kode_blok')->get();

        if ($bloks->isEmpty()) {
            $this->warn('Tidak ada blok yang ditemukan dengan kriteria filter.');

            return self::FAILURE;
        }

        $this->info("Menemukan {$bloks->count()} blok untuk diproses.");

        if ($isQueue) {
            $this->info('Mode antrean (Horizon Queue) aktif. Menjadwalkan job ke antrean...');
            $bar = $this->output->createProgressBar($bloks->count());
            $bar->start();

            foreach ($bloks as $blok) {
                GenerateForecastJob::dispatch($blok->id, $horizonMonths);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Berhasil menjadwalkan {$bloks->count()} GenerateForecastJob ke antrean Horizon.");

            return self::SUCCESS;
        }

        $this->info('Mode sinkronus aktif. Memproses data langsung...');
        $tableData = [];
        $hasError = false;

        foreach ($bloks as $blok) {
            $this->line("Memproses Blok <comment>{$blok->kode_blok}</comment>...");

            try {
                $results = $forecastingService->generateAndStoreForecast($blok, $horizonMonths);

                $first = $results->first();
                $tableData[] = [
                    'kode_blok' => $blok->kode_blok,
                    'status' => 'Sukses',
                    'bulan_proyeksi' => $results->count(),
                    'versi_model' => $first ? $first->versi_model : '-',
                    'mape_model' => $first ? number_format((float) $first->mape_model * 100, 2).'%' : '-',
                ];
            } catch (\Throwable $e) {
                $hasError = true;
                $this->error("Gagal pada Blok {$blok->kode_blok}: ".$e->getMessage());
                $tableData[] = [
                    'kode_blok' => $blok->kode_blok,
                    'status' => 'Gagal: '.$e->getMessage(),
                    'bulan_proyeksi' => 0,
                    'versi_model' => '-',
                    'mape_model' => '-',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['Blok', 'Status', 'Jml Bulan', 'Versi Model', 'MAPE'],
            $tableData
        );

        $this->info('Proses retraining selesai.');

        return $hasError ? self::FAILURE : self::SUCCESS;
    }
}
