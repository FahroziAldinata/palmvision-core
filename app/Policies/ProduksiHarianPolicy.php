<?php

namespace App\Policies;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Models\User;

class ProduksiHarianPolicy
{
    /**
     * Determine whether the user can view any production records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direksi', 'manajer_kebun', 'asisten_afdeling', 'mandor']);
    }

    /**
     * Determine whether the user can view the production record.
     */
    public function view(User $user, ProduksiHarian $produksi): bool
    {
        if ($user->hasRole('direksi')) {
            return true;
        }

        $produksi->loadMissing('blok.afdeling');
        $blok = $produksi->blok;

        if (! $blok || ! $blok->afdeling) {
            return false;
        }

        if ($user->hasRole('manajer_kebun')) {
            return $user->kebun_id === $blok->afdeling->kebun_id;
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        if ($user->hasRole('mandor')) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create production records.
     */
    public function create(User $user): bool
    {
        // Hanya mandor dan asisten yang boleh mencatat produksi
        return $user->hasAnyRole(['mandor', 'asisten_afdeling']);
    }

    /**
     * Determine whether the user can record production for a specific block.
     */
    public function createForBlok(User $user, Blok $blok): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        // Mandor dan Asisten hanya boleh menginput blok di afdelingnya
        return $user->afdeling_id === $blok->afdeling_id;
    }

    /**
     * Determine whether the user can update the production record.
     */
    public function update(User $user, ProduksiHarian $produksi): bool
    {
        $produksi->loadMissing('blok.afdeling');
        $blok = $produksi->blok;

        if (! $blok || ! $blok->afdeling) {
            return false;
        }

        // US-02 AC3: Setelah tervalidasi, mandor TIDAK bisa ubah lagi.
        if ($produksi->status_validasi === 'disetujui') {
            if ($user->hasRole('mandor')) {
                return false;
            }

            // Asisten afdeling boleh mengoreksi dengan audit trail
            if ($user->hasRole('asisten_afdeling')) {
                return $user->afdeling_id === $blok->afdeling_id;
            }

            return false;
        }

        // Jika status masih menunggu:
        if ($user->hasRole('mandor')) {
            return (int) $user->id === (int) $produksi->dicatat_oleh && $user->afdeling_id === $blok->afdeling_id;
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the production record.
     */
    public function delete(User $user, ProduksiHarian $produksi): bool
    {
        // Data yang sudah disetujui tidak boleh dihapus oleh siapapun (hanya koreksi bertahap)
        if ($produksi->status_validasi === 'disetujui') {
            return false;
        }

        $produksi->loadMissing('blok.afdeling');
        $blok = $produksi->blok;

        if (! $blok || ! $blok->afdeling) {
            return false;
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $user->afdeling_id === $blok->afdeling_id;
        }

        if ($user->hasRole('mandor')) {
            return (int) $user->id === (int) $produksi->dicatat_oleh && $user->afdeling_id === $blok->afdeling_id;
        }

        return false;
    }

    /**
     * Determine whether the user can validate/approve production records.
     */
    public function validateRecord(User $user, ProduksiHarian $produksi): bool
    {
        if (! $user->hasRole('asisten_afdeling')) {
            return false;
        }

        $produksi->loadMissing('blok');

        return $produksi->blok !== null && $user->afdeling_id === $produksi->blok->afdeling_id;
    }
}
