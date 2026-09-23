<?php

namespace App\Policies;

use App\Domain\Pemanen\Models\Pemanen;
use App\Models\User;

class PemanenPolicy
{
    /**
     * Determine whether the user can view any pemanen.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_it', 'direksi', 'manajer_kebun', 'asisten_afdeling', 'mandor']);
    }

    /**
     * Determine whether the user can view the pemanen.
     */
    public function view(User $user, Pemanen $pemanen): bool
    {
        if ($user->hasAnyRole(['admin_it', 'direksi'])) {
            return true;
        }

        if ($user->hasRole('manajer_kebun')) {
            return $user->kebun_id === $pemanen->afdeling->kebun_id;
        }

        if ($user->hasAnyRole(['asisten_afdeling', 'mandor'])) {
            return $user->afdeling_id === $pemanen->afdeling_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create a pemanen.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_it', 'asisten_afdeling']);
    }

    /**
     * Determine whether the user can update the pemanen.
     */
    public function update(User $user, Pemanen $pemanen): bool
    {
        if ($user->hasRole('admin_it')) {
            return true;
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $user->afdeling_id === $pemanen->afdeling_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the pemanen.
     */
    public function delete(User $user, Pemanen $pemanen): bool
    {
        if ($user->hasRole('admin_it')) {
            return true;
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $user->afdeling_id === $pemanen->afdeling_id;
        }

        return false;
    }
}
