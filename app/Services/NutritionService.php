<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\KondisiHarian;
use App\Models\Konsumsi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Rule-Based Nutrition Engine (SKPL-F-07).
 * Agregasi zat gizi vs AKG Permenkes 28/2019; ambang via config/nutrition.php.
 */
class NutritionService
{
    public const STATUS_DIANALISIS = 'DIANALISIS';

    public const STATUS_BELUM_ADA_DATA = 'TIDAK_DAPAT_DIANALISIS_BELUM_ADA_DATA';

    public const STATUS_ANAK_SAKIT = 'TIDAK_DAPAT_DIANALISIS_ANAK_SAKIT';

    public const STATUS_TIDAK_ADA_DATA_VALID = 'TIDAK_DAPAT_DIANALISIS_TIDAK_ADA_DATA_VALID';

    public const CATATAN = 'Status bersifat indikatif (asupan di bawah acuan AKG), bukan diagnosis defisiensi.';

    public const PENANDA_ANAK_SAKIT = 'Anak tercatat sedang sakit pada tanggal ini — data konsumsi tidak digunakan dalam analisis.';

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
     * @return array<string, mixed>
     */
    public function analisis(Anak $anak, string $tanggal): array
    {
        if (CarbonImmutable::parse($tanggal)->lt($anak->tanggal_lahir)) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tanggal analisis tidak boleh sebelum tanggal lahir anak.',
            ]);
        }

        $kondisi = KondisiHarian::query()
            ->where('anak_id', $anak->id)
            ->whereDate('tanggal_kondisi', $tanggal)
            ->first();

        $konsumsi = Konsumsi::query()
            ->with('bahan')
            ->where('anak_id', $anak->id)
            ->whereDate('tanggal_konsumsi', $tanggal)
            ->get();

        $kelompok = self::kelompokUmur(
            ZScoreService::umurBulanPenuh($anak->tanggal_lahir->toDateString(), $tanggal)
        );
        $akg = config("nutrition.akg.{$kelompok}");
        $statusKondisi = $kondisi?->status_kondisi;
        $sakit = $statusKondisi === KondisiHarian::STATUS_SAKIT;

        $dasar = [
            'tanggal' => $tanggal,
            'kelompok_umur' => $akg['label'],
            'status_kondisi' => $statusKondisi,
            'catatan' => self::CATATAN,
        ];

        if ($konsumsi->isEmpty()) {
            return array_merge($dasar, [
                'ringkasan' => [],
                'rekomendasi_tersedia' => false,
                'status_analisis' => self::STATUS_BELUM_ADA_DATA,
                'analisis_status' => 'tidak_dapat_dianalisis',
                'penanda' => null,
                'dikecualikan' => [],
            ]);
        }

        $valid = $sakit
            ? collect()
            : $konsumsi->filter(fn (Konsumsi $row) => $row->status_validitas === Konsumsi::VALID);

        $dikecualikan = $this->dikecualikan($konsumsi, $sakit);

        if ($valid->isEmpty()) {
            $status = $sakit
                ? self::STATUS_ANAK_SAKIT
                : self::STATUS_TIDAK_ADA_DATA_VALID;

            return array_merge($dasar, [
                'ringkasan' => [],
                'rekomendasi_tersedia' => false,
                'status_analisis' => $status,
                'analisis_status' => 'tidak_dapat_dianalisis',
                'penanda' => $sakit ? self::PENANDA_ANAK_SAKIT : null,
                'dikecualikan' => $dikecualikan,
            ]);
        }

        $total = [];
        foreach (array_keys(self::ZAT_GIZI) as $kolom) {
            $total[$kolom] = 0.0;
        }

        foreach ($valid as $row) {
            $faktor = ((float) $row->porsi_gram) / 100.0;

            foreach (array_keys(self::ZAT_GIZI) as $kolom) {
                $total[$kolom] += ((float) ($row->bahan->{$kolom} ?? 0.0)) * $faktor;
            }
        }

        $ringkasan = self::ringkasanDariTotal($total, $akg, $defisiensi);

        return array_merge($dasar, [
            'ringkasan' => $ringkasan,
            'rekomendasi_tersedia' => $defisiensi,
            'status_analisis' => self::STATUS_DIANALISIS,
            'analisis_status' => 'dianalisis',
            'penanda' => null,
            'dikecualikan' => $dikecualikan,
        ]);
    }

    /**
     * @param  Collection<int, Konsumsi>  $konsumsi
     * @return list<array{konsumsi_id: string, bahan_id: string, nama_bahan: string|null, alasan_invalid: string|null}>
     */
    private function dikecualikan(Collection $konsumsi, bool $sakit): array
    {
        return $konsumsi
            ->filter(fn (Konsumsi $row) => $sakit || $row->status_validitas !== Konsumsi::VALID)
            ->map(fn (Konsumsi $row) => [
                'konsumsi_id' => $row->id,
                'bahan_id' => $row->bahan_id,
                'nama_bahan' => $row->bahan?->nama_bahan,
                'alasan_invalid' => $sakit ? Konsumsi::ALASAN_ANAK_SAKIT : $row->alasan_invalid,
            ])
            ->values()
            ->all();
    }

    /**
     * Agregat bulanan: rata-rata %AKG harian antar hari tercatat.
     * Konsumsi INVALID (mis. anak sakit) tidak dihitung sebagai hari tercatat.
     *
     * @return array{bulan: string, hari_tercatat: int, hari_dikecualikan: int, rata_persen_akg: float|null, status: string, zat_terendah: string|null, rata_per_zat: array<string, float>}
     */
    public function agregatBulanan(Anak $anak, string $bulan): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            throw new \InvalidArgumentException('Format bulan harus YYYY-MM.');
        }

        $mulai = CarbonImmutable::parse($bulan.'-01')->startOfMonth();
        $selesai = $mulai->endOfMonth();

        if ($selesai->toDateString() < $anak->tanggal_lahir->toDateString()) {
            throw ValidationException::withMessages([
                'bulan' => 'Bulan analisis tidak boleh sebelum tanggal lahir anak.',
            ]);
        }

        $tanggalSakit = KondisiHarian::query()
            ->where('anak_id', $anak->id)
            ->whereDate('tanggal_kondisi', '>=', $mulai->toDateString())
            ->whereDate('tanggal_kondisi', '<=', $selesai->toDateString())
            ->where('status_kondisi', KondisiHarian::STATUS_SAKIT)
            ->get()
            ->map(fn (KondisiHarian $k) => $k->tanggal_kondisi->toDateString())
            ->all();

        $semua = Konsumsi::query()
            ->with('bahan')
            ->where('anak_id', $anak->id)
            ->whereDate('tanggal_konsumsi', '>=', $mulai->toDateString())
            ->whereDate('tanggal_konsumsi', '<=', $selesai->toDateString())
            ->orderBy('tanggal_konsumsi')
            ->get()
            ->groupBy(fn ($r) => $r->tanggal_konsumsi->toDateString());

        $rows = $semua
            ->filter(fn ($items, $tanggal) => ! in_array($tanggal, $tanggalSakit, true)
                && $items->contains(fn (Konsumsi $r) => $r->status_validitas === Konsumsi::VALID));

        if ($rows->isEmpty()) {
            return [
                'bulan' => $bulan,
                'hari_tercatat' => 0,
                'hari_dikecualikan' => $semua->count(),
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
                if ($row->status_validitas !== Konsumsi::VALID) {
                    continue;
                }

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
            'hari_dikecualikan' => $semua->count() - count($perHari),
            'rata_persen_akg' => $rata,
            'status' => $rata >= (float) config('nutrition.cutoff_cukup', 80.0)
                ? 'TERPENUHI'
                : ($rata >= (float) config('nutrition.cutoff_kurang', 70.0) ? 'KURANG TERPENUHI' : 'TIDAK TERPENUHI'),
            'zat_terendah' => $zatTerendah,
            'rata_per_zat' => $rataPerZat,
        ];
    }

    /**
     * @param  array<string, float>  $total
     * @param  array<string, mixed>  $akg
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
