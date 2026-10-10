<?php

namespace App\Policies;

use App\Models\Anak;
use App\Models\User;

class AnakPolicy
{
    /**
     * Pemilik selalu boleh. Kader boleh membaca anak dari wilayah binaannya
     * (diturunkan dari wilayah_posyandu pemilik). Admin boleh semua.
     */
    public function view(User $user, Anak $anak): bool
    {
        if ($user->isAdmin() || $anak->user_id === $user->id) {
            return true;
        }

        return $user->isKader()
            && $user->wilayah_posyandu !== null
            && $anak->user->wilayah_posyandu === $user->wilayah_posyandu;
    }

    /**
     * Ubah/hapus hanya pemilik (SKPL: PUT oleh Orang Tua) atau admin.
     */
    public function update(User $user, Anak $anak): bool
    {
        return $user->isAdmin() || $anak->user_id === $user->id;
    }

    public function delete(User $user, Anak $anak): bool
    {
        return $user->isAdmin() || $anak->user_id === $user->id;
    }
}
