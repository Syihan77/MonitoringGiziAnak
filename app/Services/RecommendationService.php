<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\KondisiHarian;
use App\Models\RekomendasiLog;
use App\Models\Resep;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid Recipe Ranking baseline (SKPL-F-08 / ML #2).
 *
 * Bobot, versi aturan, dan filter verifikasi dapat dikonfigurasi melalui
 * config/nutrition.php. Tidak ada harga, anggaran, atau biaya sebagai fitur.
 */
class RecommendationService
{
    private const NUTRISI_AKG = [
        'energi' => 'energi',
        'protein' => 'protein',
        'lemak' => 'lemak',
        'karbohidrat' => 'karbohidrat',
        'zat_besi' => 'zat_besi',
        'zinc' => 'seng',
    ];

    public function __construct(private readonly NutritionService $gizi) {}

    /**
     * @param  list<string>  $bahanTersedia
     * @return array<string, mixed>
     */
    public function rekomendasikan(
        Anak $anak,
        array $bahanTersedia = [],
        ?int $topK = null,
        ?string $tanggal = null,
        ?string $dimintaOleh = null,
    ): array {
        $tanggal ??= today()->toDateString();
        $bahanTersedia = array_values(array_unique($bahanTersedia));
        $topK = $this->batasiTopK($topK);

        $analisis = $this->gizi->analisis($anak, $tanggal);
        $sakit = $analisis['status_kondisi'] === KondisiHarian::STATUS_SAKIT;

        $defisiensi = $sakit || $analisis['status_analisis'] !== NutritionService::STATUS_DIANALISIS
            ? []
            : collect($analisis['ringkasan'])
                ->where('status', '!==', 'cukup')
                ->pluck('zat_gizi')
                ->values()
                ->all();

        $umurBulan = ZScoreService::umurBulanPenuh($anak->tanggal_lahir->toDateString(), $tanggal);
        $akg = $this->akgUntukUsia($umurBulan);
        $anak->loadMissing('pantangan');
        $alergenAnak = $anak->pantangan
            ->pluck('alergen')
            ->filter()
            ->map(fn ($nilai) => mb_strtolower(trim((string) $nilai)))
            ->unique()
            ->values()
            ->all();
        $bahanPantangan = $anak->pantangan
            ->pluck('bahan_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $punyaPantangan = $anak->pantangan->isNotEmpty();
        $kandidat = [];
        $reseps = Resep::query()
            ->with('bahans.bahan')
            ->orderBy('nama_resep')
            ->get();

        foreach ($reseps as $resep) {
            $filter = $this->filterWajib($resep, $umurBulan, $alergenAnak, $bahanPantangan, $punyaPantangan);

            if (! $filter['lolos']) {
                continue;
            }

            $persenCocok = null;
            if ($bahanTersedia !== []) {
                $bahanResep = $resep->bahans->pluck('bahan_id')->all();
                $cocok = count(array_intersect($bahanResep, $bahanTersedia));
                $persenCocok = $bahanResep === [] ? 0 : (int) round($cocok / count($bahanResep) * 100);

                if ($persenCocok === 0) {
                    continue;
                }
            }

            $gizi = $resep->giziPerPorsi();
            $skorUmum = $this->skorGiziUmum($gizi, $akg);
            $skorRelevansi = $this->skorRelevansi($gizi, $akg, $defisiensi);
            $skorAkhir = round(
                $skorUmum * (float) config('nutrition.rekomendasi.bobot_gizi_umum', 0.6)
                + $skorRelevansi * (float) config('nutrition.rekomendasi.bobot_relevansi', 0.4),
                3,
            );

            $kandidat[] = [
                'resep' => $resep,
                'gizi' => $gizi,
                'kecocokan' => $persenCocok,
                'skor_gizi_umum' => $skorUmum,
                'skor_relevansi' => $skorRelevansi,
                'skor_akhir' => $skorAkhir,
                'perlu_modifikasi' => $filter['modifikasi'],
            ];
        }

        usort($kandidat, function (array $a, array $b): int {
            return [$b['skor_akhir'], $b['kecocokan'] ?? 0, $a['resep']->nama_resep]
                <=> [$a['skor_akhir'], $a['kecocokan'] ?? 0, $b['resep']->nama_resep];
        });

        $kandidat = array_slice($kandidat, 0, $topK);
        $fokus = $defisiensi[0] ?? null;
        $menu = [];

        foreach ($kandidat as $index => $k) {
            $item = [
                'peringkat' => $index + 1,
                'resep_id' => $k['resep']->id,
                'nama_resep' => $k['resep']->nama_resep,
                'kategori_gizi' => $k['resep']->kategori_gizi,
                'tekstur_sajian' => $k['resep']->tekstur_sajian,
                'jumlah_porsi_hasil' => (int) $k['resep']->jumlah_porsi_hasil,
                'gizi_per_porsi' => $k['gizi'],
                'skor_gizi_umum' => $k['skor_gizi_umum'],
                'skor_relevansi' => $k['skor_relevansi'],
                'skor_akhir' => $k['skor_akhir'],
                'alasan_peringkat' => $this->alasanPeringkat(
                    $k['skor_gizi_umum'],
                    $k['skor_relevansi'],
                    $fokus,
                    $k['kecocokan'],
                ),
                'alergen' => $k['resep']->alergen ?? [],
                'usia_min_bulan' => $k['resep']->usia_min_bulan,
                'usia_minimal_bulan_jika_dimodifikasi' => $k['resep']->usia_minimal_bulan_jika_dimodifikasi,
                'perlu_modifikasi' => $k['perlu_modifikasi'],
                'instruksi_modifikasi' => $k['perlu_modifikasi']
                    ? $k['resep']->modifikasi_untuk_turunkan_usia
                    : null,
                'catatan_resep' => $k['resep']->catatan_resep,
                'pemeriksaan_pantangan' => $punyaPantangan ? 'dilakukan' : 'tidak_dilakukan',
                'peringatan_modifikasi' => $k['perlu_modifikasi']
                    ? 'Resep ini memerlukan modifikasi untuk anak usia '.$umurBulan
                        .' bulan. Ikuti instruksi modifikasi dan konsultasikan dengan tenaga kesehatan.'
                    : null,
            ];

            if ($k['kecocokan'] !== null) {
                $item['kecocokan_bahan'] = $k['kecocokan'].'%';
            }

            $menu[] = $item;
        }

        $saran = $menu === []
            ? 'Tidak ada resep berstatus data lengkap yang cocok untuk kriteria ini.'
            : null;

        $peringatan = $sakit
            ? 'Anak tercatat sedang sakit pada tanggal ini. Data konsumsi tidak digunakan untuk personalisasi. Konsultasikan dengan kader atau tenaga kesehatan.'
            : null;

        DB::transaction(function () use (
            $anak,
            $tanggal,
            $bahanTersedia,
            $topK,
            $defisiensi,
            $menu,
            $dimintaOleh,
            $saran,
            $fokus,
        ) {
            $versi = config('nutrition.rekomendasi.versi_aturan', 'hybrid-baseline-v1');
            $metode = RekomendasiLog::METODE_BASELINE_ATURAN;
            $jumlah = count($menu);

            if ($menu === []) {
                RekomendasiLog::create([
                    'anak_id' => $anak->id,
                    'tanggal_minta' => $tanggal,
                    'masukan' => ['bahan_tersedia' => $bahanTersedia, 'top_k' => $topK],
                    'defisiensi' => $defisiensi,
                    'fokus_gizi' => $fokus,
                    'jumlah_hasil' => 0,
                    'metode_ranking' => $metode,
                    'versi_model_atau_aturan' => $versi,
                    'alasan_peringkat' => $saran,
                    'tanggal_rekomendasi' => now(),
                    'diminta_oleh' => $dimintaOleh,
                ]);

                return;
            }

            foreach ($menu as $item) {
                RekomendasiLog::create([
                    'anak_id' => $anak->id,
                    'resep_id' => $item['resep_id'],
                    'tanggal_minta' => $tanggal,
                    'masukan' => ['bahan_tersedia' => $bahanTersedia, 'top_k' => $topK],
                    'defisiensi' => $defisiensi,
                    'fokus_gizi' => $fokus,
                    'jumlah_hasil' => $jumlah,
                    'peringkat' => $item['peringkat'],
                    'skor_gizi_umum' => $item['skor_gizi_umum'],
                    'skor_relevansi' => $item['skor_relevansi'],
                    'skor_akhir' => $item['skor_akhir'],
                    'metode_ranking' => $metode,
                    'versi_model_atau_aturan' => $versi,
                    'alasan_peringkat' => $item['alasan_peringkat'],
                    'tanggal_rekomendasi' => now(),
                    'diminta_oleh' => $dimintaOleh,
                ]);
            }
        });

        return [
            'jumlah_rekomendasi' => count($menu),
            'metode_ranking' => RekomendasiLog::METODE_BASELINE_ATURAN,
            'menu' => $menu,
            'defisiensi_terdeteksi' => $defisiensi,
            'saran' => $saran,
            'peringatan' => $peringatan,
            'pemeriksaan_pantangan' => $punyaPantangan ? 'dilakukan' : 'tidak_dilakukan',
        ];
    }

    private function batasiTopK(?int $topK): int
    {
        $maks = max(1, (int) config('nutrition.rekomendasi.top_k_maks', 5));
        $nilai = $topK ?? (int) config('nutrition.rekomendasi.top_k_default', 5);

        return max(1, min($nilai, $maks));
    }

    /**
     * @param  list<string>  $alergenAnak
     * @param  list<string>  $bahanPantangan
     * @return array{lolos: bool, modifikasi: bool}
     */
    private function filterWajib(
        Resep $resep,
        int $umurBulan,
        array $alergenAnak,
        array $bahanPantangan,
        bool $punyaPantangan,
    ): array {
        $wajibDataLengkap = (bool) config('nutrition.rekomendasi.wajib_data_lengkap', true);

        if ($wajibDataLengkap && ! in_array($resep->status_verifikasi_data, [
            Resep::VERIFIKASI_DATA_LENGKAP,
            Resep::VERIFIKASI_TERVERIFIKASI,
        ], true)) {
            return ['lolos' => false, 'modifikasi' => false];
        }

        if ($resep->usia_min_bulan === null) {
            return ['lolos' => false, 'modifikasi' => false];
        }

        $perluModifikasi = false;
        if ($umurBulan < (int) $resep->usia_min_bulan) {
            $usiaModifikasi = $resep->usia_minimal_bulan_jika_dimodifikasi;

            if ($usiaModifikasi === null
                || $umurBulan < (int) $usiaModifikasi
                || blank($resep->modifikasi_untuk_turunkan_usia)) {
                return ['lolos' => false, 'modifikasi' => false];
            }

            $perluModifikasi = true;
        }

        if ($resep->usia_maks_bulan !== null && $umurBulan > (int) $resep->usia_maks_bulan) {
            return ['lolos' => false, 'modifikasi' => false];
        }

        if ($resep->jumlah_porsi_hasil === null || (int) $resep->jumlah_porsi_hasil < 1) {
            return ['lolos' => false, 'modifikasi' => false];
        }

        foreach ($resep->giziPerPorsi() as $nilai) {
            if ($nilai === null) {
                return ['lolos' => false, 'modifikasi' => false];
            }
        }

        if ($punyaPantangan) {
            if ($resep->alergen === null) {
                return ['lolos' => false, 'modifikasi' => false];
            }

            foreach ($resep->alergen as $tag) {
                if (in_array(mb_strtolower(trim((string) $tag)), $alergenAnak, true)) {
                    return ['lolos' => false, 'modifikasi' => false];
                }
            }

            if ($bahanPantangan !== []
                && $resep->bahans->pluck('bahan_id')->intersect($bahanPantangan)->isNotEmpty()) {
                return ['lolos' => false, 'modifikasi' => false];
            }
        }

        return ['lolos' => true, 'modifikasi' => $perluModifikasi];
    }

    /**
     * @return array<string, mixed>
     */
    private function akgUntukUsia(int $umurBulan): array
    {
        $akg = config('nutrition.akg.'.NutritionService::kelompokUmur($umurBulan), []);

        return is_array($akg) ? $akg : [];
    }

    /**
     * @param  array<string, float|null>  $gizi
     * @param  array<string, mixed>  $akg
     */
    private function skorGiziUmum(array $gizi, array $akg): float
    {
        $skor = [];

        foreach (self::NUTRISI_AKG as $kunciGizi => $kunciAkg) {
            $target = (float) ($akg[$kunciAkg] ?? 0);
            $nilai = $gizi[$kunciGizi] ?? null;

            if ($target <= 0 || $nilai === null) {
                continue;
            }

            $skor[] = min(((float) $nilai) / ($target / 3.0) * 100.0, 100.0);
        }

        return $skor === [] ? 0.0 : round(array_sum($skor) / count($skor), 3);
    }

    /**
     * @param  array<string, float|null>  $gizi
     * @param  array<string, mixed>  $akg
     * @param  list<string>  $defisiensi
     */
    private function skorRelevansi(array $gizi, array $akg, array $defisiensi): float
    {
        $skor = [];

        foreach ($defisiensi as $zat) {
            $kunciGizi = $this->kunciGizi($zat);
            $kunciAkg = self::NUTRISI_AKG[$kunciGizi] ?? $zat;
            $target = (float) ($akg[$kunciAkg] ?? 0);
            $nilai = $gizi[$kunciGizi] ?? null;

            if ($target <= 0 || $nilai === null) {
                continue;
            }

            $skor[] = min(((float) $nilai) / ($target / 3.0) * 100.0, 100.0);
        }

        return $skor === [] ? 0.0 : round(array_sum($skor) / count($skor), 3);
    }

    private function kunciGizi(string $zat): string
    {
        return $zat === 'seng' ? 'zinc' : $zat;
    }

    private function alasanPeringkat(
        float $skorUmum,
        float $skorRelevansi,
        ?string $fokus,
        ?int $kecocokan,
    ): string {
        $alasan = 'Skor gizi umum '.number_format($skorUmum, 1)
            .' dan relevansi '.number_format($skorRelevansi, 1);

        if ($fokus !== null) {
            $alasan .= ' terhadap '.str_replace('_', ' ', $fokus);
        } else {
            $alasan .= '; tidak ada defisiensi terdeteksi';
        }

        if ($kecocokan !== null) {
            $alasan .= '; kecocokan bahan '.$kecocokan.'%';
        }

        return $alasan.'.';
    }
}
