<?php

namespace Database\Seeders;

use App\Models\Wilayah;
use Illuminate\Database\Seeder;

class WilayahSeeder extends Seeder
{
    /**
     * Idempoten: aman dijalankan ulang (updateOrCreate per nama).
     */
    public function run(): void
    {
        $desa = config('wilayah.desa');

        foreach (config('wilayah.daftar', []) as $nama) {
            $nama = trim((string) $nama);

            if ($nama === '') {
                continue;
            }

            Wilayah::updateOrCreate(
                ['nama' => $nama],
                ['desa' => $desa, 'aktif' => true],
            );
        }
    }
}
