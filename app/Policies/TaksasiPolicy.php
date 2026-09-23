<?php

namespace App\Policies;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;

class TaksasiPolicy
{
    /**
     * Determine whether the user can view any taksasi records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direksi', 'manajer_kebun', 'asisten_afdeling', 'kerani_taksasi']);
    }

    /**
     * Determine whether the user can view the taksasi record.
     */
    public function view(User $user, Taksasi $taksasi): bool
    {
        if ($user->hasRole('direksi')) {
            return true;
        }

        $taksasi->loadMissing('blok.afdeling');
        $blok = $taksasi->blok;

        if (! $blok || ! $blok->afdeling) {
            return false;
        }

        if ($user->hasRole('manajer_kebun')) {
            return $user->kebun_id === $blok->afdeling->kebun_id;
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        if ($user->hasRole('kerani_taksasi')) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create taksasi records.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['kerani_taksasi', 'asisten_afdeling']);
    }

    /**
     * Determine whether the user can record taksasi for a specific block.
     */
    public function createForBlok(User $user, Blok $blok): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        return $user->afdeling_id === $blok->afdeling_id;
    }
}
