<?php

namespace Database\Seeders;

use App\Models\WhoStandard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor baku rujukan WHO/Kemenkes dari MD konversi Permenkes No. 2/2020.
 *
 * Sumber: database/seeders/data/Permenkes_2_Tahun_2020_Standar_Antropometri_Anak.md
 * (relokasi dari arsip luar; isi identik, terverifikasi SHA256).
 * Hanya Tabel 1-5 (Laki-laki) & 8-12 (Perempuan) = 3 indeks bb_u/tb_u/bb_tb.
 * Tabel IMT/U (6,7,13-16) sengaja dilewati (keputusan proyek: tanpa IMT/U).
 */
class WhoStandardsSeeder extends Seeder
{
    /**
     * nomor tabel => [indikator, dasar kunci, varian].
     * dasar: 'umur' (kunci=umur_bulan) | 'panjang' (kunci=panjang_cm).
     * varian: null (bb_u) | 'pb' (rujukan 0-24 bln) | 'tb' (rujukan > 24 bln).
     *
     * @var array<int, array{0: string, 1: string, 2: string|null}>
     */
    public const TABLE_MAP = [
        1 => [WhoStandard::BB_U, 'umur', null],
        2 => [WhoStandard::TB_U, 'umur', 'pb'],
        3 => [WhoStandard::TB_U, 'umur', 'tb'],
        4 => [WhoStandard::BB_TB, 'panjang', 'pb'],
        5 => [WhoStandard::BB_TB, 'panjang', 'tb'],
        8 => [WhoStandard::BB_U, 'umur', null],
        9 => [WhoStandard::TB_U, 'umur', 'pb'],
        10 => [WhoStandard::TB_U, 'umur', 'tb'],
        11 => [WhoStandard::BB_TB, 'panjang', 'pb'],
        12 => [WhoStandard::BB_TB, 'panjang', 'tb'],
    ];

    public function run(): void
    {
        $rows = self::parseFile(self::sourcePath());

        DB::transaction(function () use ($rows) {
            WhoStandard::query()->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                WhoStandard::query()->insert($chunk);
            }
        });
    }

    public static function sourcePath(): string
    {
        return base_path(implode(DIRECTORY_SEPARATOR, [
            'database', 'seeders', 'data',
            'Permenkes_2_Tahun_2020_Standar_Antropometri_Anak.md',
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas standar WHO tidak ditemukan: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Gagal membaca berkas standar WHO: {$path}");
        }

        $rows = [];
        $tableNo = null;
        $sex = null;

        foreach ($lines as $line) {
            if (preg_match('/^####\s*Tabel\s*(\d+)\./u', trim($line), $m)) {
                $tableNo = (int) $m[1];
                $sex = null;
                continue;
            }

            if ($tableNo !== null && $sex === null
                && preg_match('/^\*\s*Anak\s+(Laki-Laki|Perempuan)\b/ui', trim($line), $m)
            ) {
                $sex = mb_strtolower($m[1]) === 'laki-laki' ? 'L' : 'P';
                continue;
            }

            if ($tableNo === null || $sex === null || ! isset(self::TABLE_MAP[$tableNo])) {
                continue;
            }

            $cells = self::parseDataRow($line);
            if ($cells === null) {
                continue;
            }

            [$indikator, $dasar, $varian] = self::TABLE_MAP[$tableNo];

            $row = [
                'indikator' => $indikator,
                'jenis_kelamin' => $sex,
                'dasar' => $dasar,
                'varian' => $varian,
                'umur_bulan' => null,
                'panjang_cm' => null,
                'sd_minus3' => $cells[1],
                'sd_minus2' => $cells[2],
                'sd_minus1' => $cells[3],
                'median' => $cells[4],
                'sd_plus1' => $cells[5],
                'sd_plus2' => $cells[6],
                'sd_plus3' => $cells[7],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($dasar === 'umur') {
                $row['umur_bulan'] = (int) $cells[0];
            } else {
                $row['panjang_cm'] = round((float) $cells[0], 1);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<float>|null 8 sel numerik [kunci, -3SD..+3SD] atau null bila bukan baris data.
     */
    private static function parseDataRow(string $line): ?array
    {
        $trimmed = trim($line);
        if (! str_starts_with($trimmed, '|')) {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode('|', $trimmed)),
            fn ($c) => $c !== ''
        ));

        if (count($parts) !== 8) {
            return null;
        }

        // Lewati baris header ("Umur (bulan)") & separator ("---").
        if (! preg_match('/^[\d.,\\\\* ]+$/u', $parts[0])) {
            return null;
        }

        $nums = [];
        foreach ($parts as $part) {
            // Baris data sah hanya berisi angka (toleransi penanda "*" dari
            // "24 \*" dan koma desimal). Sel korup (mis. "6.†" pada Tabel 12
            // baris 69.5 — footnote MD baris 1188) membuat baris dilewati;
            // ZScoreService menginterpolasi celah 0,5 cm tersebut.
            $clean = str_replace(['\\', '*', ' '], '', $part);
            if (! preg_match('/^-?\d+([.,]\d+)?$/u', $clean)) {
                return null;
            }
            $nums[] = (float) str_replace(',', '.', $clean);
        }

        return $nums;
    }
}
