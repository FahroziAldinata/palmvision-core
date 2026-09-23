<?php

namespace App\Policies;

use App\Domain\Organisasi\Models\Blok;
use App\Models\User;

class BlokPolicy
{
    /**
     * Determine whether the user can view any blok.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direksi', 'admin_it', 'gm_kebun', 'asisten_afdeling', 'mandor', 'kerani_taksasi', 'tim_gis']);
    }

    /**
     * Determine whether the user can view the blok.
     */
    public function view(User $user, Blok $blok): bool
    {
        if ($user->hasAnyRole(['direksi', 'admin_it', 'tim_gis'])) {
            return true;
        }

        if ($user->hasRole('gm_kebun')) {
            $blok->loadMissing('afdeling');

            return $blok->afdeling !== null && $user->kebun_id === $blok->afdeling->kebun_id;
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        if ($user->hasAnyRole(['mandor', 'kerani_taksasi'])) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        return false;
    }
}
