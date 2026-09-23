<?php

namespace Database\Seeders;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\GrupPerusahaan;
use App\Domain\Organisasi\Models\Kebun;
use App\Domain\Organisasi\Models\PoligonBlok;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles
        $roles = [
            'direksi',
            'manajer_kebun',
            'asisten_afdeling',
            'mandor',
            'kerani_taksasi',
            'tim_gis',
            'admin_it',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 2. Grup Perusahaan
        $grup = GrupPerusahaan::firstOrCreate(
            ['nama' => 'PT Palma Nusantara Jaya'],
            ['npwp' => '01.234.567.8-901.000']
        );

        // 3. Kebun (2 kebun)
        $kebun1 = Kebun::firstOrNew(['kode_kebun' => 'KBS']);
        $kebun1->grup_id = $grup->id;
        $kebun1->nama = 'Kebun Bukit Sentosa';
        $kebun1->koordinat_pusat = DB::raw('ST_SetSRID(ST_MakePoint(101.4500, 0.5333), 4326)');
        $kebun1->save();

        $kebun2 = Kebun::firstOrNew(['kode_kebun' => 'KRM']);
        $kebun2->grup_id = $grup->id;
        $kebun2->nama = 'Kebun Rawa Makmur';
        $kebun2->koordinat_pusat = DB::raw('ST_SetSRID(ST_MakePoint(101.6500, 0.6500), 4326)');
        $kebun2->save();

        // 4. Afdeling (2 per kebun)
        $afdelingsData = [
            ['kebun_id' => $kebun1->id, 'kode' => 'AFD-A', 'nama' => 'Afdeling Alpha', 'baseLng' => 101.440, 'baseLat' => 0.530, 'codePrefix' => 'A'],
            ['kebun_id' => $kebun1->id, 'kode' => 'AFD-B', 'nama' => 'Afdeling Beta', 'baseLng' => 101.440, 'baseLat' => 0.537, 'codePrefix' => 'B'],
            ['kebun_id' => $kebun2->id, 'kode' => 'AFD-C', 'nama' => 'Afdeling Gamma', 'baseLng' => 101.640, 'baseLat' => 0.640, 'codePrefix' => 'C'],
            ['kebun_id' => $kebun2->id, 'kode' => 'AFD-D', 'nama' => 'Afdeling Delta', 'baseLng' => 101.640, 'baseLat' => 0.647, 'codePrefix' => 'D'],
        ];

        $createdAfdelings = [];

        foreach ($afdelingsData as $afdInfo) {
            $afdeling = Afdeling::firstOrCreate(
                ['kebun_id' => $afdInfo['kebun_id'], 'kode' => $afdInfo['kode']],
                ['nama' => $afdInfo['nama']]
            );

            $createdAfdelings[$afdInfo['kode']] = $afdeling;

            // 5. 5 Blok per afdeling
            for ($i = 1; $i <= 5; $i++) {
                $kodeBlok = sprintf('%s%02d', $afdInfo['codePrefix'], $i);
                $luasHa = 25.00 + ($i * 0.75);
                $jumlahPokok = (int) round($luasHa * 135);
                $tanggalTanam = Carbon::create(2017, 1, 15)->addMonths($i * 4)->toDateString();
                $kategoriTanah = ($i % 2 === 0) ? 'Gambut' : 'Mineral';

                $blok = Blok::firstOrCreate(
                    ['afdeling_id' => $afdeling->id, 'kode_blok' => $kodeBlok],
                    [
                        'luas_ha' => $luasHa,
                        'tanggal_tanam' => $tanggalTanam,
                        'jumlah_pokok' => $jumlahPokok,
                        'kategori_tanah' => $kategoriTanah,
                    ]
                );

                // Poligon PostGIS (tertutup, valid, non-intersecting)
                $lng0 = $afdInfo['baseLng'] + (($i - 1) * 0.005);
                $lng1 = $afdInfo['baseLng'] + ($i * 0.005);
                $lat0 = $afdInfo['baseLat'];
                $lat1 = $afdInfo['baseLat'] + 0.005;

                $wkt = sprintf(
                    'POLYGON((%.6f %.6f, %.6f %.6f, %.6f %.6f, %.6f %.6f, %.6f %.6f))',
                    $lng0, $lat0,
                    $lng1, $lat0,
                    $lng1, $lat1,
                    $lng0, $lat1,
                    $lng0, $lat0
                );

                $existingPoligon = PoligonBlok::where('blok_id', $blok->id)->where('versi', 1)->first();
                if (! $existingPoligon) {
                    $poligon = new PoligonBlok;
                    $poligon->blok_id = $blok->id;
                    $poligon->versi = 1;
                    $poligon->diperbarui_pada = Carbon::create(2026, 9, 1)->toDateString();
                    $poligon->poligon = DB::raw("ST_SetSRID(ST_GeomFromText('{$wkt}'), 4326)");
                    $poligon->save();
                }
            }
        }

        // 6. Users Seed
        $password = Hash::make('password');

        // Direksi
        $direksi = User::firstOrCreate(
            ['email' => 'direksi@palmvision.test'],
            [
                'name' => 'Bambang Soedirgo (Direksi)',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );
        $direksi->syncRoles(['direksi']);

        // Manajer Kebun 1
        $manajerSentosa = User::firstOrCreate(
            ['email' => 'manajer.sentosa@palmvision.test'],
            [
                'name' => 'Hendro Wijaya (Manajer KBS)',
                'password' => $password,
                'kebun_id' => $kebun1->id,
                'email_verified_at' => now(),
            ]
        );
        $manajerSentosa->syncRoles(['manajer_kebun']);

        // Manajer Kebun 2
        $manajerMakmur = User::firstOrCreate(
            ['email' => 'manajer.makmur@palmvision.test'],
            [
                'name' => 'Agus Pratama (Manajer KRM)',
                'password' => $password,
                'kebun_id' => $kebun2->id,
                'email_verified_at' => now(),
            ]
        );
        $manajerMakmur->syncRoles(['manajer_kebun']);

        // Asisten Afdeling Alpha (Kebun 1)
        $asistenAlpha = User::firstOrCreate(
            ['email' => 'asisten.alpha@palmvision.test'],
            [
                'name' => 'Rian Syahputra (Asisten Alpha)',
                'password' => $password,
                'kebun_id' => $kebun1->id,
                'afdeling_id' => $createdAfdelings['AFD-A']->id,
                'email_verified_at' => now(),
            ]
        );
        $asistenAlpha->syncRoles(['asisten_afdeling']);
        $createdAfdelings['AFD-A']->update(['asisten_id' => $asistenAlpha->id]);

        // Asisten Afdeling Beta (Kebun 1)
        $asistenBeta = User::firstOrCreate(
            ['email' => 'asisten.beta@palmvision.test'],
            [
                'name' => 'Dedi Kurniawan (Asisten Beta)',
                'password' => $password,
                'kebun_id' => $kebun1->id,
                'afdeling_id' => $createdAfdelings['AFD-B']->id,
                'email_verified_at' => now(),
            ]
        );
        $asistenBeta->syncRoles(['asisten_afdeling']);
        $createdAfdelings['AFD-B']->update(['asisten_id' => $asistenBeta->id]);

        // Mandor
        $mandor = User::firstOrCreate(
            ['email' => 'mandor@palmvision.test'],
            [
                'name' => 'Joko Susanto (Mandor Panen)',
                'password' => $password,
                'kebun_id' => $kebun1->id,
                'afdeling_id' => $createdAfdelings['AFD-A']->id,
                'email_verified_at' => now(),
            ]
        );
        $mandor->syncRoles(['mandor']);

        // Kerani Taksasi
        $kerani = User::firstOrCreate(
            ['email' => 'kerani@palmvision.test'],
            [
                'name' => 'Siti Aminah (Kerani Taksasi)',
                'password' => $password,
                'kebun_id' => $kebun1->id,
                'afdeling_id' => $createdAfdelings['AFD-A']->id,
                'email_verified_at' => now(),
            ]
        );
        $kerani->syncRoles(['kerani_taksasi']);

        // Tim GIS
        $gis = User::firstOrCreate(
            ['email' => 'gis@palmvision.test'],
            [
                'name' => 'Fajar Ramadhan (Spesialis GIS)',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );
        $gis->syncRoles(['tim_gis']);

        // Admin IT
        $admin = User::firstOrCreate(
            ['email' => 'admin@palmvision.test'],
            [
                'name' => 'Administrator IT',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin_it']);
    }
}
