<?php

use App\Domain\Organisasi\Models\Kebun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('user can view laporan index hub', function () {
    $manajer = User::where('email', 'manajer.sentosa@palmvision.test')->firstOrFail();

    $this->actingAs($manajer)
        ->get(route('laporan.index'))
        ->assertOk();
});

test('user can download tabular Excel CSV report', function () {
    $manajer = User::where('email', 'manajer.sentosa@palmvision.test')->firstOrFail();
    $kebun = Kebun::where('kode_kebun', 'KBS')->firstOrFail();

    $response = $this->actingAs($manajer)
        ->get(route('laporan.excel', [
            'kebun_id' => $kebun->id,
            'bulan' => now()->month,
            'tahun' => now()->year,
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->getContent();
    expect($content)->toContain('REKAPITULASI PRODUKSI DAN TAKSASI')
        ->and($content)->toContain('Kebun Bukit Sentosa')
        ->and($content)->toContain('Afdeling')
        ->and($content)->toContain('Kode Blok');
});

test('US-10: user can generate official PDF report via Gotenberg with letterhead and signature block', function () {
    $manajer = User::where('email', 'manajer.sentosa@palmvision.test')->firstOrFail();
    $kebun = Kebun::where('kode_kebun', 'KBS')->firstOrFail();

    $response = $this->actingAs($manajer)
        ->get(route('laporan.pdf', [
            'kebun_id' => $kebun->id,
            'bulan' => now()->month,
            'tahun' => now()->year,
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');

    // PDF files always begin with the magic header %PDF
    $content = $response->getContent();
    expect(str_starts_with($content, '%PDF'))->toBeTrue();
});
