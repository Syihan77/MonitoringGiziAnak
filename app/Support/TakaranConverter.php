<?php

namespace App\Support;

final class TakaranConverter
{
    private const PECAHAN = [
        '½' => 0.5,
        '¼' => 0.25,
        '¾' => 0.75,
        '⅓' => 1 / 3,
        '⅔' => 2 / 3,
        '⅛' => 0.125,
        '⅜' => 0.375,
        '⅝' => 0.625,
        '⅞' => 0.875,
    ];

    private const PELENGKAP = [
        'garam',
        'gula',
        'air',
        'asi',
        'merica',
        'lada',
        'ketumbar',
        'jinten',
        'kemiri',
        'bunga lawang',
        'jahe',
        'kunyit',
        'laos',
        'lengkuas',
        'sereh',
        'serai',
        'santan',
        'daun salam',
        'daun jeruk',
        'daun pandan',
        'daun bawang',
        'daun pisang',
        'seledri',
        'lokio',
        'bawang goreng',
        'bawang merah',
        'bawang putih',
        'bawang bombay',
        'kaldu',
        'kecap',
        'saus',
        'minyak',
        'margarin',
        'tepung roti',
        'panir',
        'rempah',
        'vanila',
    ];

    /**
     * @return array{nama: string, jumlah: ?float, satuan: ?string, gram: ?float, teks: string}
     */
    public static function parse(string $baris): array
    {
        $teks = trim($baris);
        [$nama, $kuantitas] = self::pisah($teks);
        $utama = trim(preg_replace('/\([^)]*\)/u', ' ', $kuantitas) ?? $kuantitas);

        $jumlah = self::angka($utama);
        $satuan = self::satuan($utama);
        $gram = self::gramEksplisit($kuantitas);

        if ($gram !== null && $jumlah === null) {
            $jumlah = $gram;
            $satuan = 'g';
        }

        if ($gram === null && $jumlah !== null) {
            $gram = self::gram($nama, $jumlah, $satuan);
        }

        return [
            'nama' => trim($nama),
            'jumlah' => $jumlah,
            'satuan' => $satuan,
            'gram' => $gram,
            'teks' => $teks,
        ];
    }

    public static function pelengkap(string $nama): bool
    {
        $nama = self::normalisasi($nama);

        foreach (self::PELENGKAP as $kata) {
            if (preg_match('/\b'.preg_quote($kata, '/').'\b/u', $nama) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function normalisasi(string $nama): string
    {
        $nama = preg_replace('/\([^)]*\)/u', ' ', $nama) ?? $nama;
        $nama = mb_strtolower($nama);
        $nama = preg_replace('/[^a-z0-9À-ÿ\s]/u', ' ', $nama) ?? $nama;
        $nama = preg_replace('/\s+/u', ' ', $nama) ?? $nama;

        return trim($nama);
    }

    public static function angka(?string $teks): ?float
    {
        if ($teks === null) {
            return null;
        }

        $teks = trim($teks);
        if ($teks === '') {
            return null;
        }

        if (preg_match('/(\d+)\s+(\d+)\s*\/\s*(\d+)/u', $teks, $m)) {
            return (float) $m[1] + ((float) $m[2] / (float) $m[3]);
        }

        if (preg_match('/(\d+)\s*\/\s*(\d+)/u', $teks, $m)) {
            return (float) $m[1] / (float) $m[2];
        }

        foreach (self::PECAHAN as $karakter => $nilai) {
            if (str_contains($teks, $karakter)) {
                return $nilai;
            }
        }

        if (preg_match('/(\d+[.,]\d+)/u', $teks, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        if (preg_match('/(\d+)/u', $teks, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private static function pisah(string $teks): array
    {
        $level = 0;
        $panjang = mb_strlen($teks);

        for ($i = 0; $i < $panjang; $i++) {
            $karakter = mb_substr($teks, $i, 1);

            if ($karakter === '(') {
                $level++;
            } elseif ($karakter === ')') {
                $level = max(0, $level - 1);
            } elseif ($karakter === ':' && $level === 0) {
                return [
                    mb_substr($teks, 0, $i),
                    mb_substr($teks, $i + 1),
                ];
            }
        }

        return [$teks, ''];
    }

    private static function satuan(string $kuantitas): ?string
    {
        foreach ([
            'sdm', 'sdt', 'gram', 'kg', 'ml', 'gls', 'gelas', 'butir', 'siung',
            'lembar', 'batang', 'ruas', 'potong', 'ptg', 'buah', 'ikat', 'ekor',
            'bungkus', 'sachet', 'porsi', 'mangkok', 'ons',
        ] as $satuan) {
            if (preg_match('/\b'.preg_quote($satuan, '/').'\b/u', mb_strtolower($kuantitas)) === 1) {
                return $satuan;
            }
        }

        if (preg_match('/\b(gr|g)\b/u', mb_strtolower($kuantitas)) === 1) {
            return 'g';
        }

        if (preg_match('/\bcm\b/u', mb_strtolower($kuantitas)) === 1) {
            return 'cm';
        }

        return null;
    }

    private static function gramEksplisit(string $kuantitas): ?float
    {
        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(?:gr|g)\b/iu', $kuantitas, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private static function gram(string $nama, float $jumlah, ?string $satuan): ?float
    {
        $nama = self::normalisasi($nama);
        $satuan = $satuan ?? 'g';

        $faktor = match ($satuan) {
            'kg' => 1000.0,
            'g', 'gram', 'ml' => 1.0,
            'sdm' => 15.0,
            'sdt' => 5.0,
            'gls', 'gelas' => 200.0,
            'mangkok' => 200.0,
            'ons' => 100.0,
            'ikat' => 100.0,
            'bungkus', 'sachet' => 5.0,
            'porsi' => 100.0,
            'siung' => str_contains($nama, 'bawang putih') ? 4.0 : 5.0,
            'butir' => self::beratButir($nama),
            'lembar' => 1.0,
            'batang' => str_contains($nama, 'daun bawang') || str_contains($nama, 'seledri') ? 15.0 : 10.0,
            'ruas' => 5.0,
            'cm' => 3.0,
            'potong', 'ptg' => self::beratPotong($nama),
            'buah' => self::beratBuah($nama),
            'ekor' => 50.0,
            default => 1.0,
        };

        return round($jumlah * $faktor, 2);
    }

    private static function beratButir(string $nama): float
    {
        if (str_contains($nama, 'puyuh')) {
            return 10.0;
        }

        if (str_contains($nama, 'telur') || str_contains($nama, 'ayam')) {
            return 50.0;
        }

        return 10.0;
    }

    private static function beratPotong(string $nama): float
    {
        if (str_contains($nama, 'ikan') || str_contains($nama, 'tuna') || str_contains($nama, 'gabus')) {
            return 40.0;
        }

        if (str_contains($nama, 'ayam') || str_contains($nama, 'daging')) {
            return 30.0;
        }

        if (str_contains($nama, 'tahu') || str_contains($nama, 'tempe')) {
            return 30.0;
        }

        return 30.0;
    }

    private static function beratBuah(string $nama): float
    {
        return match (true) {
            str_contains($nama, 'pisang') => 80.0,
            str_contains($nama, 'tomat') => 80.0,
            str_contains($nama, 'wortel') => 60.0,
            str_contains($nama, 'jeruk') => 100.0,
            str_contains($nama, 'apel') => 100.0,
            str_contains($nama, 'naga') => 150.0,
            str_contains($nama, 'labu') => 100.0,
            default => 100.0,
        };
    }
}
