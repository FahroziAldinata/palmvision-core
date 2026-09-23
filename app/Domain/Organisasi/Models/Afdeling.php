<?php

namespace App\Domain\Organisasi\Models;

use App\Domain\Pemanen\Models\Pemanen;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Afdeling extends Model
{
    use HasUuids;

    protected $table = 'afdeling';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kebun_id',
        'kode',
        'nama',
        'asisten_id',
    ];

    /**
     * @return BelongsTo<Kebun, $this>
     */
    public function kebun(): BelongsTo
    {
        return $this->belongsTo(Kebun::class, 'kebun_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function asisten(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asisten_id');
    }

    /**
     * @return HasMany<Blok, $this>
     */
    public function bloks(): HasMany
    {
        return $this->hasMany(Blok::class, 'afdeling_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'afdeling_id');
    }

    /**
     * @return HasMany<Pemanen, $this>
     */
    public function pemanens(): HasMany
    {
        return $this->hasMany(Pemanen::class, 'afdeling_id');
    }
}
