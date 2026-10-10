<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Akun admin awal (registrasi publik hanya untuk orang_tua/kader).
     * Password admin HANYA dari environment (tidak ada default di repo).
     * Set ADMIN_PASSWORD di .env sebelum menjalankan db:seed.
     */
    public function run(): void
    {
        $pw = env('ADMIN_PASSWORD');

        if (! $pw) {
            throw new \RuntimeException('Set ADMIN_PASSWORD di .env sebelum db:seed.');
        }

        User::firstOrCreate(
            ['email' => 'admin@gizi.local'],
            [
                'nama' => 'Administrator',
                'password' => $pw,
                'role' => User::ROLE_ADMIN,
                'wilayah_posyandu' => null,
                'no_telp' => null,
                'consent_approved' => true,
            ],
        );
    }
}
