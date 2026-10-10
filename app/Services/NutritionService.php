<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\Konsumsi;

/**
 * Rule-Based Nutrition Engine (SKPL-F-07).
 * Agregasi zat gizi vs AKG Permenkes 28/2019; ambang via config/nutrition.php.
 */
class NutritionService
{
    /**
     * Zat gizi yang dianalisis: kolom bahan => kunci AKG/respons.
     *
     * @var array<string, string>
     */
    public const ZAT_GIZI = [
        'energi_per100g' => 'energi',
        'protein_per100g' => 'protein',
        'lemak_per100g' => 'lemak',
        'karbohidrat_per100g' => 'karbohidrat',
        'zat_besi_per100g' => 'zat_besi',
        'zinc_per100g' => 'seng',
    ];

    /**
     * @return array{tanggal: string, kelompok_umur: string, ringkasan: list<array{zat_gizi: string, total: float, akg: float, persentase_akg: float, status: string}>, rekomendasi_tersedia: bool, analisis_status: string}
     */
    public function analisis(Anak $anak, string $tanggal): array
    {
        $konsumsi = Konsumsi::query()
            ->with('bahan')
            ->where('anak_id', $anak->id)
            ->where('tanggal_konsumsi', $tanggal)
            ->get();        $kelompok = self::kelompokUmur(
            ZScoreService::umurBulanPenuh($anak->tanggal_lahir->toDateString(), $tanggal)
        );
        $akg = config("nutrition.akg.{$kelompok}");

        if ($konsumsi->isEmpty()) {
            return [
                'tanggal' => $tanggal,
                'kelompok_umur' => $akg['label'],
                'ringkasan' => [],
                'rekomendasi_tersedia' => false,
                'analisis_status' => 'tidak_dapat_dianalisis',
            ];
        }

        $total = [];
        foreach (array_keys(self::ZAT_GIZI) as $kolom) {
            $total[$kolom] = 0.0;
        }

        foreach ($konsumsi as $row) {
            $faktor = ((float) $row->porsi_gram) / 100.0;

            foreach (array_keys(self::ZAT_GIZI) as $kolom) {
                $total[$kolom] += ((float) ($row->bahan->{$kolom} ?? 0.0)) * $faktor;
            }
        }

        $ringkasan = self::ringkasanDariTotal($total, $akg, $defisiensi);

        return [
            'tanggal' => $tanggal,
            'kelompok_umur' => $akg['label'],
            'ringkasan' => $ringkasan,
            'rekomendasi_tersedia' => $defisiensi,
            'analisis_status' => 'dianalisis',
        ];
    }

    /**
     * Agregat bulanan: rata-rata %AKG harian antar hari tercatat.
     *
     * @return array{bulan: string, hari_tercatat: int, rata_persen_akg: float|null, status: string, zat_terendah: string|null, rata_per_zat: array<string, float>}
     */
    public function agregatBulanan(Anak $anak, string $bulan): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            throw new \InvalidArgumentException('Format bulan harus YYYY-MM.');
        }

        $rows = Konsumsi::query()
            ->with('bahan')
            ->where('anak_id', $anak->id)
            ->where('tanggal_konsumsi', 'like', "{$bulan}-%")
            ->orderBy('tanggal_konsumsi')
            ->get()
            ->groupBy(fn ($r) => $r->tanggal_konsumsi->toDateString());

        if ($rows->isEmpty()) {
            return [
                'bulan' => $bulan,
                'hari_tercatat' => 0,
                'rata_persen_akg' => null,
                'status' => 'belum_tercatat',
                'zat_terendah' => null,
                'rata_per_zat' => [],
            ];
        }

        $perHari = [];
        foreach ($rows as $tanggal => $items) {
            $umur = ZScoreService::umurBulanPenuh($anak->tanggal_lahir->toDateString(), $tanggal);
            $akg = config('nutrition.akg.'.self::kelompokUmur($umur));

            $total = [];
            foreach (array_keys(self::ZAT_GIZI) as $kolom) {
                $total[$kolom] = 0.0;
            }
            foreach ($items as $row) {
                $faktor = ((float) $row->porsi_gram) / 100.0;
                foreach (array_keys(self::ZAT_GIZI) as $kolom) {
                    $total[$kolom] += ((float) ($row->bahan->{$kolom} ?? 0.0)) * $faktor;
                }
            }

            $persen = [];
            foreach (self::ZAT_GIZI as $kolom => $nama) {
                $target = (float) $akg[$nama];
                // Cap 100%: semantik pemenuhan — kelebihan satu zat tidak boleh
                // menyeret rata-rata (mis. seng 5000% -> rata 2315%).
                $persen[$nama] = $target > 0 ? min($total[$kolom] / $target * 100.0, 100.0) : 0.0;
            }
            $perHari[] = $persen;
        }

        $rataPerZat = [];
        foreach (array_values(self::ZAT_GIZI) as $nama) {
            $rataPerZat[$nama] = round(
                collect($perHari)->avg(fn ($p) => $p[$nama] ?? 0.0), 1
            );
        }

        $rata = round(collect($rataPerZat)->avg(), 1);
        $tanpaEnergi = collect($rataPerZat)->except('energi');
        $zatTerendah = $tanpaEnergi->sort()->keys()->first();

        return [
            'bulan' => $bulan,
            'hari_tercatat' => count($perHari),
            'rata_persen_akg' => $rata,
            'status' => $rata >= (float) config('nutrition.cutoff_cukup', 80.0)
                ? 'TERPENUHI'
                : ($rata >= (float) config('nutrition.cutoff_kurang', 70.0) ? 'KURANG TERPENUHI' : 'TIDAK TERPENUHI'),
            'zat_terendah' => $zatTerendah,
            'rata_per_zat' => $rataPerZat,
        ];
    }

    /**
     * @param array<string, float> $total
     * @param array<string, mixed> $akg
     * @return list<array{zat_gizi: string, total: float, akg: float, persentase_akg: float, status: string}>
     */
    private static function ringkasanDariTotal(array $total, array $akg, ?bool &$defisiensi = null): array
    {
        $ringkasan = [];
        $defisiensi = false;

        foreach (self::ZAT_GIZI as $kolom => $nama) {
            $target = (float) $akg[$nama];
            $persen = $target > 0 ? round($total[$kolom] / $target * 100.0, 1) : 0.0;
            $status = self::status($persen);

            if ($nama !== 'energi' && $status !== 'cukup') {
                $defisiensi = true;
            }

            $ringkasan[] = [
                'zat_gizi' => $nama,
                'total' => round($total[$kolom], 2),
                'akg' => $target,
                'persentase_akg' => $persen,
                'status' => $status,
            ];
        }

        return $ringkasan;
    }

    public static function kelompokUmur(int $umurBulan): string
    {
        if ($umurBulan < 6) {
            return '0-5bln';
        }
        if ($umurBulan < 12) {
            return '6-11bln';
        }
        if ($umurBulan < 48) {
            return '1-3th';
        }

        return '4-6th';
    }

    public static function status(float $persen): string
    {
        if ($persen >= (float) config('nutrition.cutoff_cukup', 80.0)) {
            return 'cukup';
        }
        if ($persen >= (float) config('nutrition.cutoff_kurang', 70.0)) {
            return 'kurang';
        }

        return 'sangat_kurang';
    }
}
