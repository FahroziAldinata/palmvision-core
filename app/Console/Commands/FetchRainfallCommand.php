<?php

namespace App\Console\Commands;

use App\Domain\Organisasi\Models\Kebun;
use App\Services\RainfallService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FetchRainfallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rainfall:fetch
                            {kebun_id? : ID atau Kode Kebun yang akan diambil data curah hujannya}
                            {--start-date= : Tanggal mulai (YYYY-MM-DD), default 18 bulan lalu}
                            {--end-date= : Tanggal selesai (YYYY-MM-DD), default kemarin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ambil dan simpan data curah hujan historis harian dari Open-Meteo Archive API ke database lokal';

    /**
     * Execute the console command.
     */
    public function handle(RainfallService $rainfallService): int
    {
        $kebunId = $this->argument('kebun_id');
        $startDate = $this->option('start-date') ?? now()->subMonths(18)->startOfMonth()->toDateString();
        $endDate = $this->option('end-date') ?? now()->subDay()->toDateString();

        $this->info("Memulai sinkronisasi data curah hujan Open-Meteo ({$startDate} s/d {$endDate})...");

        $query = Kebun::query();
        if ($kebunId) {
            $query->where(function ($q) use ($kebunId) {
                if (Str::isUuid($kebunId)) {
                    $q->where('id', $kebunId);
                } else {
                    $q->where('kode_kebun', $kebunId);
                }
            });
        }

        $kebuns = $query->get();
        if ($kebuns->isEmpty()) {
            $this->warn('Tidak ada kebun yang ditemukan.');

            return self::FAILURE;
        }

        foreach ($kebuns as $kebun) {
            $this->line("-> Mengunduh data untuk Kebun: {$kebun->nama} ({$kebun->kode_kebun})...");
            $count = $rainfallService->fetchAndStore($kebun, $startDate, $endDate);
            $this->info("   Berhasil menyimpan {$count} catatan harian curah hujan.");
        }

        $this->info('Sinkronisasi curah hujan selesai.');

        return self::SUCCESS;
    }
}
