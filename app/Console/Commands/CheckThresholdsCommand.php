<?php

namespace App\Console\Commands;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;
use App\Notifications\MissedHarvestRotationNotification;
use App\Notifications\TaksasiDeviationNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CheckThresholdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'palmvision:check-thresholds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cek ambang batas selisih taksasi (>15%) dan keterlambatan rotasi panen, lalu kirim notifikasi in-app dan email.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memulai pengecekan ambang batas PalmVision...');

        $deviationCount = $this->checkTaksasiDeviations();
        $rotationCount = $this->checkMissedRotations();

        $this->info("Pengecekan selesai: {$deviationCount} notifikasi deviasi taksasi, {$rotationCount} notifikasi rotasi terlewat terkirim.");

        return Command::SUCCESS;
    }

    /**
     * Cek selisih taksasi vs realisasi > 15%.
     */
    protected function checkTaksasiDeviations(): int
    {
        $notified = 0;
        // Ambil taksasi dalam 7 hari terakhir
        $recentTaksasis = Taksasi::with(['blok.afdeling.kebun'])
            ->where('tanggal_taksasi', '>=', now()->subDays(7)->toDateString())
            ->get();

        foreach ($recentTaksasis as $taksasi) {
            $blok = $taksasi->blok;
            if (! $blok) {
                continue;
            }

            // Cari produksi harian pada tanggal taksasi yang sama
            $produksiList = ProduksiHarian::with('details')
                ->where('blok_id', $blok->id)
                ->where('tanggal', $taksasi->tanggal_taksasi->toDateString())
                ->get();

            if ($produksiList->isEmpty()) {
                continue;
            }

            $realisasiKg = $produksiList->sum(fn ($p) => $p->total_berat_kg);
            $estimasiKg = (float) $taksasi->estimasi_total_kg;

            if ($estimasiKg <= 0) {
                continue;
            }

            $selisihKg = abs($realisasiKg - $estimasiKg);
            $persentaseSelisih = round(($selisihKg / $estimasiKg) * 100, 2);

            if ($persentaseSelisih > 15.0) {
                // Cari target notifikasi: Asisten Afdeling dan Manajer Kebun terkait
                $targetUsers = $this->getTargetUsers($blok);

                foreach ($targetUsers as $user) {
                    // Hindari duplikasi notifikasi untuk kombinasi taksasi ini hari ini
                    $alreadyNotified = $user->notifications()
                        ->where('data->type', 'taksasi_deviation')
                        ->where('data->blok_id', $blok->id)
                        ->where('data->tanggal', $taksasi->tanggal_taksasi->toDateString())
                        ->whereDate('created_at', now()->toDateString())
                        ->exists();

                    if (! $alreadyNotified) {
                        $user->notify(new TaksasiDeviationNotification(
                            $blok,
                            $taksasi->tanggal_taksasi->toDateString(),
                            $estimasiKg,
                            $realisasiKg,
                            $persentaseSelisih
                        ));
                        $notified++;
                    }
                }
            }
        }

        return $notified;
    }

    /**
     * Cek blok yang melewati siklus rotasi panen.
     */
    protected function checkMissedRotations(): int
    {
        $notified = 0;
        $bloks = Blok::with(['afdeling.kebun'])->get();

        foreach ($bloks as $blok) {
            $latestProduksi = ProduksiHarian::where('blok_id', $blok->id)
                ->latest('tanggal')
                ->first();

            if (! $latestProduksi) {
                continue;
            }

            $kebun = $blok->afdeling?->kebun;
            $siklusRotasiHari = $kebun ? $kebun->siklus_rotasi_hari : 10;
            $tanggalTerakhirPanen = Carbon::parse($latestProduksi->tanggal);
            $tanggalRotasiSeharusnya = $tanggalTerakhirPanen->copy()->addDays($siklusRotasiHari);

            $hariTerlewat = abs((int) now()->diffInDays($tanggalRotasiSeharusnya));

            if ($tanggalRotasiSeharusnya->isPast() && $hariTerlewat >= 1) {
                $targetUsers = $this->getTargetUsers($blok);

                foreach ($targetUsers as $user) {
                    // Hindari duplikasi notifikasi rotasi dalam 24 jam terakhir untuk blok ini
                    $alreadyNotified = $user->notifications()
                        ->where('data->type', 'missed_rotation')
                        ->where('data->blok_id', $blok->id)
                        ->where('created_at', '>=', now()->subHours(24))
                        ->exists();

                    if (! $alreadyNotified) {
                        $user->notify(new MissedHarvestRotationNotification(
                            $blok,
                            $tanggalRotasiSeharusnya->toDateString(),
                            $hariTerlewat
                        ));
                        $notified++;
                    }
                }
            }
        }

        return $notified;
    }

    /**
     * Get relevant users (asisten afdeling, manajer kebun, admin) for a given blok.
     *
     * @return Collection<int, User>
     */
    protected function getTargetUsers(Blok $blok)
    {
        return User::query()
            ->where(function ($query) use ($blok) {
                $query->where(function ($q) use ($blok) {
                    $q->role('asisten_afdeling')
                        ->where('afdeling_id', $blok->afdeling_id);
                })->orWhere(function ($q) use ($blok) {
                    $q->role('manajer_kebun')
                        ->where('kebun_id', $blok->afdeling?->kebun_id);
                })->orWhereHas('roles', function ($q) {
                    $q->whereIn('name', ['superadmin', 'administrator']);
                });
            })
            ->get();
    }
}
