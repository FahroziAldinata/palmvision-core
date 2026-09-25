<?php

namespace App\Domain\Forecasting\Models;

use App\Domain\Organisasi\Models\Blok;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $blok_id
 * @property CarbonInterface|string $periode
 * @property float $nilai_kg
 * @property float $interval_bawah
 * @property float $interval_atas
 * @property float $mape_model
 * @property string $versi_model
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Blok|null $blok
 */
class ForecastResult extends Model
{
    use HasUuids;

    protected $table = 'forecast_result';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blok_id',
        'periode',
        'nilai_kg',
        'interval_bawah',
        'interval_atas',
        'mape_model',
        'versi_model',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'nilai_kg' => 'float',
            'interval_bawah' => 'float',
            'interval_atas' => 'float',
            'mape_model' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Blok, $this>
     */
    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id');
    }
}
