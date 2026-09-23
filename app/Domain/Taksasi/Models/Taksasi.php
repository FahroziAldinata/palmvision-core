<?php

namespace App\Domain\Taksasi\Models;

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
 * @property CarbonInterface|string $tanggal_taksasi
 * @property int $pokok_disampel
 * @property int $estimasi_janjang
 * @property float $estimasi_bjr
 * @property float $estimasi_total_kg
 * @property string|null $catatan
 * @property-read Blok|null $blok
 * @property-read User|null $pencatat
 */
class Taksasi extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'taksasi';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blok_id',
        'dicatat_oleh',
        'tanggal_taksasi',
        'pokok_disampel',
        'estimasi_janjang',
        'estimasi_bjr',
        'estimasi_total_kg',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_taksasi' => 'date',
            'pokok_disampel' => 'integer',
            'estimasi_janjang' => 'integer',
            'estimasi_bjr' => 'decimal:2',
            'estimasi_total_kg' => 'decimal:2',
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
