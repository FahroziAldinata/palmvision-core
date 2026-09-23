<?php

namespace App\Domain\Produksi\Models;

use App\Domain\Pemanen\Models\Pemanen;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $produksi_harian_id
 * @property string $pemanen_id
 * @property int $jumlah_janjang
 * @property float $berat_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProduksiHarian $produksiHarian
 * @property-read Pemanen $pemanen
 */
class ProduksiHarianDetail extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'produksi_harian_detail';

    protected $fillable = [
        'produksi_harian_id',
        'pemanen_id',
        'jumlah_janjang',
        'berat_kg',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'jumlah_janjang' => 'integer',
        'berat_kg' => 'float',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['jumlah_janjang', 'berat_kg', 'pemanen_id'])
            ->logOnlyDirty();
    }

    /**
     * @return BelongsTo<ProduksiHarian, $this>
     */
    public function produksiHarian(): BelongsTo
    {
        return $this->belongsTo(ProduksiHarian::class, 'produksi_harian_id');
    }

    /**
     * @return BelongsTo<Pemanen, $this>
     */
    public function pemanen(): BelongsTo
    {
        return $this->belongsTo(Pemanen::class, 'pemanen_id');
    }
}
