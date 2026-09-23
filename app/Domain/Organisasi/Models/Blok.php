<?php

namespace App\Domain\Organisasi\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $afdeling_id
 * @property string $kode_blok
 * @property float $luas_ha
 * @property CarbonInterface|string|null $tanggal_tanam
 * @property int $jumlah_pokok
 * @property string $kategori_tanah
 * @property-read Afdeling|null $afdeling
 * @property-read PoligonBlok|null $latestPoligon
 */
class Blok extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'blok';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'afdeling_id',
        'kode_blok',
        'luas_ha',
        'tanggal_tanam',
        'jumlah_pokok',
        'kategori_tanah',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'luas_ha' => 'decimal:2',
            'tanggal_tanam' => 'date',
            'jumlah_pokok' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Afdeling, $this>
     */
    public function afdeling(): BelongsTo
    {
        return $this->belongsTo(Afdeling::class, 'afdeling_id');
    }

    /**
     * @return HasMany<PoligonBlok, $this>
     */
    public function poligonBloks(): HasMany
    {
        return $this->hasMany(PoligonBlok::class, 'blok_id');
    }

    /**
     * @return HasOne<PoligonBlok, $this>
     */
    public function latestPoligon(): HasOne
    {
        return $this->hasOne(PoligonBlok::class, 'blok_id')->orderByDesc('versi');
    }
}
