<?php

namespace App\Domain\Integrasi\Pks\Models;

use App\Domain\Organisasi\Models\Kebun;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $no_tiket_timbangan
 * @property string|null $kebun_id
 * @property CarbonInterface|string $tanggal_terima
 * @property float $berat_tbs_terima_kg
 * @property float $rendemen_cpo_persen
 * @property float $rendemen_pk_persen
 * @property float $ffa_persen
 * @property string|null $catatan
 * @property array<string, mixed>|null $payload_raw
 * @property-read Kebun|null $kebun
 */
class PksRendemen extends Model
{
    use HasUuids;

    protected $table = 'pks_rendemen';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'no_tiket_timbangan',
        'kebun_id',
        'tanggal_terima',
        'berat_tbs_terima_kg',
        'rendemen_cpo_persen',
        'rendemen_pk_persen',
        'ffa_persen',
        'catatan',
        'payload_raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_terima' => 'date',
            'berat_tbs_terima_kg' => 'decimal:2',
            'rendemen_cpo_persen' => 'decimal:2',
            'rendemen_pk_persen' => 'decimal:2',
            'ffa_persen' => 'decimal:2',
            'payload_raw' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Kebun, $this>
     */
    public function kebun(): BelongsTo
    {
        return $this->belongsTo(Kebun::class, 'kebun_id');
    }
}
