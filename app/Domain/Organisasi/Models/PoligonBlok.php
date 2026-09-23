<?php

namespace App\Domain\Organisasi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PoligonBlok extends Model
{
    use HasUuids;

    protected $table = 'poligon_blok';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blok_id',
        'poligon',
        'versi',
        'diperbarui_pada',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'versi' => 'integer',
            'diperbarui_pada' => 'date',
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
     * Scope a query to include the GeoJSON representation of the polygon.
     *
     * @param  Builder<PoligonBlok>  $query
     * @return Builder<PoligonBlok>
     */
    public function scopeWithGeoJson(Builder $query): Builder
    {
        return $query->select('*', DB::raw('ST_AsGeoJSON(poligon) as geojson'));
    }
}
