<?php

namespace App\Support;

use App\Models\BahanMakanan;
use Illuminate\Database\Eloquent\Builder;

final class BahanResolver
{
    /**
     * @var list<string>
     */
    public const KOLOM_GIZI = [
        'energi_per100g',
        'protein_per100g',
        'lemak_per100g',
        'karbohidrat_per100g',
        'zat_besi_per100g',
        'zinc_per100g',
    ];

    public const LABEL_PLACEHOLDER = 'Dataset resep berlabel';

    public static function cari(string $nama, bool $buatPlaceholder = true): ?BahanMakanan
    {
        $normal = mb_strtolower($nama);
        $alias = AliasBahan::target($nama);

        $bahan = self::referensi()
            ->whereRaw('LOWER(nama_bahan) = ?', [$alias])
            ->first();

        if ($bahan === null && ! in_array($alias, AliasBahan::TANPA_FUZZY, true)) {
            $bahan = self::referensi()
                ->whereRaw('LOWER(nama_bahan) LIKE ?', ['%'.$alias.'%'])
                ->orderByRaw('CASE WHEN LOWER(nama_bahan) LIKE ? THEN 0 ELSE 1 END', [$alias.'%'])
                ->orderByRaw('CASE WHEN energi_per100g IS NULL THEN 1 ELSE 0 END')
                ->orderByRaw('LENGTH(nama_bahan)')
                ->orderBy('nama_bahan')
                ->first();
        }

        if ($bahan !== null) {
            return $bahan;
        }

        $lama = BahanMakanan::whereRaw('LOWER(nama_bahan) = ?', [$normal])->first();

        if ($lama === null && $alias !== $normal) {
            $lama = BahanMakanan::whereRaw('LOWER(nama_bahan) = ?', [$alias])->first();
        }

        if ($lama !== null || ! $buatPlaceholder) {
            return $lama;
        }

        $namaPlaceholder = $alias !== $normal
            ? mb_convert_case($alias, MB_CASE_TITLE)
            : $nama;

        return BahanMakanan::firstOrCreate(
            ['nama_bahan' => $namaPlaceholder],
            [
                'kelompok_gizi' => 'Dataset',
                'sumber_rujukan' => self::LABEL_PLACEHOLDER,
            ]
        );
    }

    public static function targetBackfill(BahanMakanan $placeholder): ?BahanMakanan
    {
        $target = AliasBahan::targetBackfill($placeholder->nama_bahan);

        if ($target === null) {
            return null;
        }

        return self::referensi()
            ->whereRaw('LOWER(nama_bahan) = ?', [$target])
            ->first();
    }

    public static function placeholder(BahanMakanan $bahan): bool
    {
        return str_starts_with((string) $bahan->sumber_rujukan, self::LABEL_PLACEHOLDER);
    }

    public static function kosongSemua(BahanMakanan $bahan): bool
    {
        foreach (self::KOLOM_GIZI as $kolom) {
            if ($bahan->{$kolom} !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Builder<BahanMakanan>
     */
    private static function referensi()
    {
        return BahanMakanan::query()->where(fn ($q) => $q
            ->whereNull('sumber_rujukan')
            ->orWhere('sumber_rujukan', 'not like', self::LABEL_PLACEHOLDER.'%'));
    }
}
