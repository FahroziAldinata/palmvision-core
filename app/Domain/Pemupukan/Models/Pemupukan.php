<?php

namespace App\Domain\Pemupukan\Models;

use App\Domain\Organisasi\Models\Blok;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $blok_id
 * @property int $dicatat_oleh
 * @property CarbonInterface|string $tanggal_aplikasi
 * @property string $jenis_pupuk
 * @property float $dosis_kg_per_pokok
 * @property int $jumlah_pokok_dipupuk
 * @property float $total_kg_terpakai
 * @property string $cara_aplikasi
 * @property string|null $catatan
 * @property-read Blok|null $blok
 * @property-read User|null $pencatat
 */
class Pemupukan extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'pemupukan';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blok_id',
        'dicatat_oleh',
        'tanggal_aplikasi',
        'jenis_pupuk',
        'dosis_kg_per_pokok',
        'jumlah_pokok_dipupuk',
        'total_kg_terpakai',
        'cara_aplikasi',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_aplikasi' => 'date',
            'dosis_kg_per_pokok' => 'decimal:2',
            'jumlah_pokok_dipupuk' => 'integer',
            'total_kg_terpakai' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Blok, $this>
     */
    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
