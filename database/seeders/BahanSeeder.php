<?php

namespace Database\Seeders;

use App\Models\BahanMakanan;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Impor referensi bahan dari TKPI 2020 (1146 baris).
 * Sumber: database/seeders/data/TKPI_2020_komposisi_pangan.csv
 * (relokasi dari arsip luar; isi identik, terverifikasi SHA256).
 * Sel kosong -> NULL (deviasi terdokumentasi di migrasi).
 */
class BahanSeeder extends Seeder
{
    public function run(): void
    {
        $rows = self::parseFile(self::sourcePath());

        // Upsert per nama (bukan delete+insert) agar aman dijalankan ulang
        // di atas data referensi yang sudah dipakai resep/konsumsi (FK restrict).
        foreach (array_chunk($rows, 500) as $chunk) {
            BahanMakanan::upsert($chunk, ['nama_bahan'], [
                'kelompok_gizi', 'energi_per100g', 'karbohidrat_per100g',
                'protein_per100g', 'lemak_per100g', 'zat_besi_per100g',
                'zinc_per100g', 'sumber_rujukan', 'updated_at',
            ]);
        }
    }

    public static function sourcePath(): string
    {
        return base_path(implode(DIRECTORY_SEPARATOR, [
            'database', 'seeders', 'data', 'TKPI_2020_komposisi_pangan.csv',
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas TKPI tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Gagal membaca berkas TKPI: {$path}");
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('Berkas TKPI kosong.');
        }

        // Hilangkan BOM di sel pertama.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map('trim', $header);

        $idx = array_flip($header);
        foreach (['kode_pangan', 'nama_bahan', 'kelompok', 'energi_kkal', 'protein_g', 'lemak_g', 'kh_g', 'besi_mg', 'seng_mg'] as $kolom) {
            if (! isset($idx[$kolom])) {
                fclose($handle);

                throw new RuntimeException("Kolom TKPI hilang: {$kolom}");
            }
        }

        $rows = [];
        while (($sel = fgetcsv($handle)) !== false) {
            if (count($sel) < count($header)) {
                continue;
            }

            $nama = trim((string) ($sel[$idx['nama_bahan']] ?? ''));
            if ($nama === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'nama_bahan' => mb_substr($nama, 0, 100),
                'kelompok_gizi' => mb_substr(trim((string) ($sel[$idx['kelompok']] ?? '')) ?: 'Lainnya', 0, 50),
                'energi_per100g' => self::angka($sel[$idx['energi_kkal']] ?? null),
                'karbohidrat_per100g' => self::angka($sel[$idx['kh_g']] ?? null),
                'protein_per100g' => self::angka($sel[$idx['protein_g']] ?? null),
                'lemak_per100g' => self::angka($sel[$idx['lemak_g']] ?? null),
                'zat_besi_per100g' => self::angka($sel[$idx['besi_mg']] ?? null),
                'zinc_per100g' => self::angka($sel[$idx['seng_mg']] ?? null),
                'estimasi_harga_per100g' => null,
                'sumber_rujukan' => mb_substr('TKPI 2020 '.trim((string) ($sel[$idx['kode_pangan']] ?? '')), 0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($handle);

        return $rows;
    }

    private static function angka(mixed $nilai): ?float
    {
        $s = trim((string) ($nilai ?? ''));

        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
