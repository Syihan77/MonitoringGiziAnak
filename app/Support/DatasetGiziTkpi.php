<?php

namespace App\Support;

use RuntimeException;

final class DatasetGiziTkpi
{
    public const CATATAN = 'Gizi dihitung dari TKPI 2020 (estimasi), belum diverifikasi ahli gizi';

    public const KOLOM = [
        'energi_kcal_per_porsi' => 'energi_per_porsi',
        'karbohidrat_g_per_porsi' => 'karbohidrat_per_porsi',
        'protein_g_per_porsi' => 'protein_per_porsi',
        'lemak_g_per_porsi' => 'lemak_per_porsi',
        'zat_besi_mg_per_porsi' => 'zat_besi_per_porsi',
        'vitamin_c_mg_per_porsi' => 'vitamin_c_per_porsi',
        'kalsium_mg_per_porsi' => 'kalsium_per_porsi',
        'zinc_mg_per_porsi' => 'zinc_per_porsi',
        'vitamin_a_RE_per_porsi' => 'vitamin_a_per_porsi',
        'serat_g_per_porsi' => 'serat_per_porsi',
    ];

    private const NOL_TIDAK_TERSEDIA = [
        'zat_besi_per_porsi',
        'kalsium_per_porsi',
        'zinc_per_porsi',
        'serat_per_porsi',
    ];

    public static function path(): string
    {
        return base_path(implode(DIRECTORY_SEPARATOR, [
            'database', 'seeders', 'data',
            'dataset_resep_gizi_terstandarisasi_berlabelusia_tkpi.csv',
        ]));
    }

    /**
     * @return list<array<string, string>>
     */
    public static function rows(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            throw new RuntimeException("Berkas dataset TKPI tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Gagal membaca berkas dataset TKPI: {$path}");
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('Berkas dataset TKPI kosong.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($nama) => trim((string) $nama), $header);
        $hasil = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }

            $row = array_pad($row, count($header), null);
            $data = array_combine($header, array_map(
                fn ($nilai) => trim((string) ($nilai ?? '')),
                $row,
            ));

            if ($data === false || ($data['id_resep'] ?? '') === '') {
                continue;
            }

            $hasil[] = $data;
        }

        fclose($handle);

        return $hasil;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, float|null>
     */
    public static function gizi(array $row): array
    {
        $hasil = [];

        foreach (self::KOLOM as $kolomCsv => $kolomDb) {
            $nilai = self::angka($row[$kolomCsv] ?? null);
            $hasil[$kolomDb] = $nilai === 0.0 && in_array($kolomDb, self::NOL_TIDAK_TERSEDIA, true)
                ? null
                : $nilai;
        }

        return $hasil;
    }

    public static function catatan(?string $catatan): ?string
    {
        $catatan = trim((string) $catatan);
        $catatan = preg_replace(
            '/\s*Catatan data:.*?\(normalisasi basis ML:[^)]*\)\./u',
            '',
            $catatan,
        ) ?? $catatan;
        $catatan = trim($catatan);

        if (str_contains($catatan, self::CATATAN)) {
            return $catatan;
        }

        return $catatan === '' ? self::CATATAN : $catatan.' '.self::CATATAN;
    }

    private static function angka(?string $nilai): ?float
    {
        $teks = trim((string) ($nilai ?? ''));

        if ($teks === '' || ! is_numeric(str_replace(',', '.', $teks))) {
            return null;
        }

        return (float) str_replace(',', '.', $teks);
    }
}
