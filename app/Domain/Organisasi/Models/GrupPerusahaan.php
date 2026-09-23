<?php

namespace App\Domain\Organisasi\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupPerusahaan extends Model
{
    use HasUuids;

    protected $table = 'grup_perusahaan';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'npwp',
    ];

    /**
     * @return HasMany<Kebun, $this>
     */
    public function kebuns(): HasMany
    {
        return $this->hasMany(Kebun::class, 'grup_id');
    }
}
