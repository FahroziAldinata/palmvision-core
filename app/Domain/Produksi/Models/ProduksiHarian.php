<?php

namespace App\Domain\Produksi\Models;

use App\Domain\Organisasi\Models\Blok;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $blok_id
 * @property int $dicatat_oleh
 * @property CarbonInterface|string $tanggal
 * @property string $status_validasi
 * @property string|null $catatan
 * @property string $sumber
 * @property string|null $client_uuid
 * @property-read int $total_janjang
 * @property-read float $total_berat_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Blok|null $blok
 * @property-read User|null $pencatat
 * @property-read Collection<int, ProduksiHarianDetail> $details
 */
class ProduksiHarian extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'produksi_harian';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status_validasi', 'catatan'])
            ->logOnlyDirty();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blok_id',
        'dicatat_oleh',
        'tanggal',
        'status_validasi',
        'catatan',
        'sumber',
        'client_uuid',
        'device_time',
        'perlu_tinjauan_waktu',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'device_time' => 'datetime',
        'perlu_tinjauan_waktu' => 'boolean',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'total_janjang',
        'total_berat_kg',
    ];

    /**
     * Accessor for total janjang across all harvesters.
     */
    public function getTotalJanjangAttribute(): int
    {
        if ($this->relationLoaded('details')) {
            return (int) $this->details->sum('jumlah_janjang');
        }

        return (int) $this->details()->sum('jumlah_janjang');
    }

    /**
     * Accessor for total berat kg across all harvesters.
     */
    public function getTotalBeratKgAttribute(): float
    {
        if ($this->relationLoaded('details')) {
            return (float) $this->details->sum('berat_kg');
        }

        return (float) $this->details()->sum('berat_kg');
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

    /**
     * @return HasMany<ProduksiHarianDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(ProduksiHarianDetail::class, 'produksi_harian_id');
    }
}
