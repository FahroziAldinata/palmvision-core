<?php

namespace App\Policies;

use App\Domain\Organisasi\Models\Afdeling;
use App\Models\User;

class AfdelingPolicy
{
    /**
     * Determine whether the user can view any afdeling.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direksi', 'admin_it', 'gm_kebun', 'asisten_afdeling']);
    }

    /**
     * Determine whether the user can view the afdeling.
     */
    public function view(User $user, Afdeling $afdeling): bool
    {
        if ($user->hasAnyRole(['direksi', 'admin_it', 'tim_gis'])) {
            return true;
        }

        if ($user->hasRole('gm_kebun')) {
            return $user->kebun_id === $afdeling->kebun_id;
        }

        if ($user->hasRole('asisten_afdeling')) {
            // US-09 AC3: Asisten Afdeling A cannot view Afdeling B even in the same kebun
            return $user->afdeling_id === $afdeling->id;
        }

        if ($user->hasAnyRole(['mandor', 'kerani_taksasi'])) {
            return $user->afdeling_id === $afdeling->id;
        }

        return false;
    }
}
