<?php

namespace App\Domain\Pemanen\Models;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $afdeling_id
 * @property string $nama
 * @property string $kode_pemanen
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Afdeling $afdeling
 * @property-read Collection<int, ProduksiHarianDetail> $produksiDetails
 */
class Pemanen extends Model
{
    use HasUuids;

    protected $table = 'pemanen';

    protected $fillable = [
        'afdeling_id',
        'nama',
        'kode_pemanen',
        'status',
    ];

    /**
     * @return BelongsTo<Afdeling, $this>
     */
    public function afdeling(): BelongsTo
    {
        return $this->belongsTo(Afdeling::class, 'afdeling_id');
    }

    /**
     * @return HasMany<ProduksiHarianDetail, $this>
     */
    public function produksiDetails(): HasMany
    {
        return $this->hasMany(ProduksiHarianDetail::class, 'pemanen_id');
    }
}
