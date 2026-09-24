<?php

namespace App\Domain\Organisasi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Kebun extends Model
{
    use HasUuids;

    protected $table = 'kebun';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grup_id',
        'kode_kebun',
        'nama',
        'koordinat_pusat',
        'siklus_rotasi_hari',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'siklus_rotasi_hari' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GrupPerusahaan, $this>
     */
    public function grup(): BelongsTo
    {
        return $this->belongsTo(GrupPerusahaan::class, 'grup_id');
    }

    /**
     * @return HasMany<Afdeling, $this>
     */
    public function afdelings(): HasMany
    {
        return $this->hasMany(Afdeling::class, 'kebun_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'kebun_id');
    }

    /**
     * @return HasManyThrough<Blok, Afdeling, $this>
     */
    public function bloks(): HasManyThrough
    {
        return $this->hasManyThrough(Blok::class, Afdeling::class, 'kebun_id', 'afdeling_id');
    }
}
