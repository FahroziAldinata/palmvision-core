<?php

namespace App\Policies;

use App\Domain\Organisasi\Models\Kebun;
use App\Models\User;

class KebunPolicy
{
    /**
     * Determine whether the user can view any kebun.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direksi', 'admin_it', 'gm_kebun']);
    }

    /**
     * Determine whether the user can view the kebun.
     */
    public function view(User $user, Kebun $kebun): bool
    {
        if ($user->hasAnyRole(['direksi', 'admin_it', 'tim_gis'])) {
            return true;
        }

        if ($user->hasRole('gm_kebun')) {
            return $user->kebun_id === $kebun->id;
        }

        if ($user->hasAnyRole(['asisten_afdeling', 'mandor', 'kerani_taksasi'])) {
            return $user->kebun_id === $kebun->id;
        }

        return false;
    }
}
