<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;
use App\Notifications\MissedHarvestRotationNotification;
use App\Notifications\TaksasiDeviationNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('check-thresholds command dispatches notification when taksasi vs realisasi deviation exceeds 15 percent', function () {
    Notification::fake();

    $afdeling = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blok = Blok::where('afdeling_id', $afdeling->id)->firstOrFail();
    $kerani = User::where('email', 'kerani@palmvision.test')->firstOrFail();
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdeling->id)->firstOrFail();

    $tanggal = now()->toDateString();

    // 1. Taksasi: 1.000 kg
    Taksasi::create([
        'blok_id' => $blok->id,
        'dicatat_oleh' => $kerani->id,
        'tanggal_taksasi' => $tanggal,
        'pokok_disampel' => 10,
        'estimasi_janjang' => 50,
        'estimasi_bjr' => 20,
        'estimasi_total_kg' => 1000.0,
    ]);

    // 2. Realisasi Panen: 1.250 kg (deviasi 25%, > 15%)
    $produksi = ProduksiHarian::create([
        'blok_id' => $blok->id,
        'dicatat_oleh' => $mandor->id,
        'tanggal' => $tanggal,
        'status_validasi' => 'menunggu',
    ]);

    ProduksiHarianDetail::create([
        'produksi_harian_id' => $produksi->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 60,
        'berat_kg' => 1250.0,
    ]);

    // Run artisan command
    $this->artisan('palmvision:check-thresholds')
        ->assertSuccessful();

    // Verify TaksasiDeviationNotification sent to asisten afdeling
    $asisten = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    Notification::assertSentTo($asisten, TaksasiDeviationNotification::class, function ($notification) use ($blok) {
        return $notification->blok->id === $blok->id
            && $notification->persentaseSelisih === 25.0;
    });
});

test('check-thresholds command dispatches notification when harvest rotation is overdue', function () {
    Notification::fake();

    $afdeling = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blok = Blok::where('afdeling_id', $afdeling->id)->firstOrFail();
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();

    // Last harvest was 15 days ago (kebun rotation cycle is 10 days -> 5 days overdue)
    ProduksiHarian::create([
        'blok_id' => $blok->id,
        'dicatat_oleh' => $mandor->id,
        'tanggal' => now()->subDays(15)->toDateString(),
        'status_validasi' => 'disetujui',
    ]);

    $this->artisan('palmvision:check-thresholds')
        ->assertSuccessful();

    $asisten = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    Notification::assertSentTo($asisten, MissedHarvestRotationNotification::class);
});

test('user can fetch notifications and mark as read via api', function () {
    $asisten = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $blok = Blok::firstOrFail();

    // Real send to test database storage
    $asisten->notify(new TaksasiDeviationNotification(
        $blok,
        now()->toDateString(),
        1000.0,
        1250.0,
        25.0
    ));

    expect($asisten->unreadNotifications()->count())->toBe(1);

    // GET /api/v1/notifications
    $response = $this->actingAs($asisten, 'sanctum')
        ->getJson(route('api.notifications.index'));

    $response->assertOk()
        ->assertJsonPath('unread_count', 1);

    $notifId = $response->json('notifications.data.0.id');

    // Mark as read
    $readResponse = $this->actingAs($asisten, 'sanctum')
        ->postJson(route('api.notifications.read', $notifId));

    $readResponse->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($asisten->fresh()->unreadNotifications()->count())->toBe(0);
});
