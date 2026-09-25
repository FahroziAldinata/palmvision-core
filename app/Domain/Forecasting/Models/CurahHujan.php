<?php

namespace App\Domain\Forecasting\Models;

use App\Domain\Organisasi\Models\Kebun;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $kebun_id
 * @property CarbonInterface|string $tanggal
 * @property float $curah_hujan_mm
 * @property string $sumber
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Kebun|null $kebun
 */
class CurahHujan extends Model
{
    use HasUuids;

    protected $table = 'curah_hujan';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kebun_id',
        'tanggal',
        'curah_hujan_mm',
        'sumber',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'curah_hujan_mm' => 'decimal:2',
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
