<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\BahanMakanan;
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

    /**
     * Nutrisi yang dikenal kontrak ML2. Defisiensi di luar AKG lokal tetap dapat
     * diteruskan sebagai prioritas ML2 (mis. vitamin C) tanpa mengubah skor lokal.
     *
     * @var list<string>
     */
    private const NUTRISI_ML2 = [
        // Energi tidak lagi menjadi prioritas ML2; lihat $pemicu di rekomendasikan().
        'protein',
        'lemak',
        'karbohidrat',
        'zat_besi',
        'zinc',
        'vitamin_c',
        'kalsium',
        'vitamin_a',
        'serat',
    ];

    public function __construct(
        private readonly NutritionService $gizi,
        private readonly MlClient $ml,
    ) {}

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
                ->whereIn('status', ['kurang', 'sangat_kurang'])
                ->pluck('zat_gizi')
                ->values()
                ->all();

        // Energi tetap dinilai pada skor gizi umum, tetapi bukan pemicu
        // rekomendasi/ML2. Hanya defisiensi non-energi yang menjadi fokus.
        $pemicu = array_values(array_diff($defisiensi, ['energi']));

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
        $namaBahanTersedia = $this->namaBahan($bahanTersedia);
        $bahanDihindari = $this->bahanDihindari($anak, $bahanPantangan);
        $bolehDemo = ! app()->environment('production')
            && (bool) config('nutrition.rekomendasi.demo_allow_data_lengkap', false);
        $demoDigunakan = false;
        $kandidat = [];
        $reseps = Resep::query()
            ->with('bahans.bahan')
            ->orderBy('nama_resep')
            ->get();

        foreach ($reseps as $resep) {
            $filter = $this->filterWajib(
                $resep,
                $umurBulan,
                $alergenAnak,
                $bahanPantangan,
                $punyaPantangan,
                $bolehDemo,
            );

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
            $skorRelevansi = $this->skorRelevansi($gizi, $akg, $pemicu);
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

        $kandidatLokal = $kandidat;
        $ml2 = $this->panggilMl2(
            $namaBahanTersedia,
            $bahanDihindari,
            $pemicu,
            $kandidatLokal,
        );
        $terpilih = $this->pilihMenu($kandidatLokal, $ml2['items'], $topK);
        $ml2Mendominasi = collect($terpilih)->contains(
            fn (array $k) => $k['sumber_ranking'] === 'ML2'
        );
        $metode = $ml2Mendominasi
            ? RekomendasiLog::METODE_MODEL_ML
            : RekomendasiLog::METODE_BASELINE_ATURAN;
        $jumlahMl2 = collect($terpilih)->where('sumber_ranking', 'ML2')->count();
        $blokMl2 = [
            'tersedia' => $ml2Mendominasi,
            'alasan_tidak_tersedia' => $ml2Mendominasi ? null : $ml2['alasan'],
            'status_model' => 'EKSPERIMEN',
            'jenis_ranking' => 'PERSENTIL_CONTENT_BASED',
            'status_validasi' => 'BELUM_DIVALIDASI_AHLI_GIZI',
            'versi_model' => $ml2['versi_model'],
            'metode' => 'baseline',
            'jumlah_dikembalikan' => $ml2['jumlah_dikembalikan'],
            'jumlah_dipakai' => $jumlahMl2,
        ];
        $fokus = $pemicu[0] ?? null;
        $menu = [];

        foreach ($terpilih as $index => $k) {
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
                'sumber_ranking' => $k['sumber_ranking'],
                'alasan_peringkat' => $this->alasanPeringkat(
                    $k['skor_gizi_umum'],
                    $k['skor_relevansi'],
                    $fokus,
                    $k['kecocokan'],
                ).($k['sumber_ranking'] === 'ML2' ? ' Diurutkan oleh ML2.' : ''),
                'alergen' => $k['resep']->alergen ?? [],
                'usia_min_bulan' => $k['resep']->usia_min_bulan,
                'usia_label_bulan' => $k['resep']->usia_label_bulan,
                'usia_minimal_bulan_jika_dimodifikasi' => $k['resep']->usia_minimal_bulan_jika_dimodifikasi,
                'label_data' => $k['resep']->dataLengkap()
                    ? 'DEMO_BELUM_TERVERIFIKASI'
                    : 'TERVERIFIKASI',
                'status_verifikasi_data' => $k['resep']->status_verifikasi_data,
                'cara_memasak' => $k['resep']->cara_memasak,
                'bahans' => $k['resep']->bahans->map(fn ($rb) => [
                    'bahan_id' => $rb->bahan_id,
                    'nama_bahan' => $rb->bahan->nama_bahan,
                    'takaran_jumlah' => $rb->takaran_jumlah,
                    'takaran_satuan' => $rb->takaran_satuan,
                    'takaran_gram_ekuivalen' => $rb->takaran_gram_ekuivalen,
                    'catatan_bahan' => $rb->catatan_bahan,
                ])->values(),
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

            if (($k['skor_ml2'] ?? null) !== null) {
                $item['skor_ml2'] = $k['skor_ml2'];
            }

            if ($k['resep']->dataLengkap()) {
                $demoDigunakan = true;
            }

            $menu[] = $item;
        }

        $saran = $menu === []
            ? 'Belum ada resep terverifikasi yang cocok untuk kriteria ini.'
            : null;

        $peringatan = $sakit
            ? 'Anak tercatat sedang sakit pada tanggal ini. Data konsumsi tidak digunakan untuk personalisasi. Konsultasikan dengan kader atau tenaga kesehatan.'
            : null;

        DB::transaction(function () use (
            $anak,
            $tanggal,
            $bahanTersedia,
            $topK,
            $menu,
            $dimintaOleh,
            $saran,
            $fokus,
            $metode,
            $ml2,
            $bahanDihindari,
        ) {
            $versi = $metode === RekomendasiLog::METODE_MODEL_ML
                ? 'ml2-'.($ml2['versi_model'] ?? 'unknown')
                : (string) config('nutrition.rekomendasi.versi_aturan', 'hybrid-baseline-v1');
            $masukan = [
                'bahan_tersedia' => $bahanTersedia,
                'bahan_dihindari' => $bahanDihindari,
                'top_k' => $topK,
                'ml2_tersedia' => $ml2['tersedia'],
                'ml2_alasan_tidak_tersedia' => $ml2['alasan'],
            ];
            $jumlah = count($menu);

            if ($menu === []) {
                RekomendasiLog::create([
                    'anak_id' => $anak->id,
                    'tanggal_minta' => $tanggal,
                    'masukan' => $masukan,
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
                    'masukan' => $masukan,
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

        $hasil = [
            'jumlah_rekomendasi' => count($menu),
            'metode_ranking' => $metode,
            'menu' => $menu,
            'defisiensi_terdeteksi' => $pemicu,
            'saran' => $saran,
            'peringatan' => $peringatan,
            'pemeriksaan_pantangan' => $punyaPantangan ? 'dilakukan' : 'tidak_dilakukan',
            'ml2' => $blokMl2,
        ];

        if ($demoDigunakan) {
            $hasil['mode_demo'] = true;
            $hasil['label_data'] = 'DEMO_BELUM_TERVERIFIKASI';
            $hasil['peringatan_demo'] = 'Sebagian resep belum terverifikasi tenaga kesehatan; hasil ini hanya untuk demo non-produksi.';
        }

        return $hasil;
    }

    private function batasiTopK(?int $topK): int
    {
        $maks = max(1, (int) config('nutrition.rekomendasi.top_k_maks', 5));
        $nilai = $topK ?? (int) config('nutrition.rekomendasi.top_k_default', 5);

        return max(1, min($nilai, $maks));
    }

    /**
     * @param  list<string>  $bahanTersedia
     * @return list<string>
     */
    private function namaBahan(array $bahanTersedia): array
    {
        if ($bahanTersedia === []) {
            return [];
        }

        $nama = BahanMakanan::query()
            ->whereIn('id', $bahanTersedia)
            ->pluck('nama_bahan', 'id');
        $hasil = [];

        foreach (array_values(array_unique($bahanTersedia)) as $id) {
            $nilai = $nama->get($id);

            if (is_string($nilai) && trim($nilai) !== '') {
                $hasil[] = trim($nilai);
            }
        }

        return $hasil;
    }

    /**
     * Nama bahan pantangan/alergen diteruskan ke ML2 sebagai lapisan tambahan.
     * filterWajib() tetap penentu akhir; bahan_dihindari tidak menggantikannya.
     *
     * @param  list<string>  $bahanPantangan
     * @return list<string>
     */
    private function bahanDihindari(Anak $anak, array $bahanPantangan): array
    {
        $alergen = $anak->pantangan
            ->pluck('alergen')
            ->filter(fn ($nilai) => is_string($nilai) && trim($nilai) !== '')
            ->map(fn ($nilai) => mb_strtolower(trim((string) $nilai)))
            ->values()
            ->all();

        return array_values(array_unique(array_merge($this->namaBahan($bahanPantangan), $alergen)));
    }

    /**
     * @param  list<string>  $namaBahanTersedia
     * @param  list<string>  $bahanDihindari
     * @param  list<string>  $defisiensi
     * @param  list<array<string, mixed>>  $kandidatLokal
     * @return array{tersedia: bool, alasan: string|null, versi_model: string|null, jumlah_dikembalikan: int, items: list<array{id_resep: string, skor_ml2: float|null}>}
     */
    private function panggilMl2(
        array $namaBahanTersedia,
        array $bahanDihindari,
        array $defisiensi,
        array $kandidatLokal,
    ): array {
        if (! filter_var(config('services.ml.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return $this->tanpaMl2('disabled');
        }

        if ($defisiensi === []) {
            return $this->tanpaMl2('tanpa_defisiensi');
        }

        $prioritas = [];
        foreach ($defisiensi as $zat) {
            $kunci = $this->kunciGizi((string) $zat);

            if (in_array($kunci, self::NUTRISI_ML2, true)) {
                $prioritas[$kunci] = ($prioritas[$kunci] ?? 0.0) + 1.0;
            }
        }

        if ($prioritas === []) {
            return $this->tanpaMl2('tanpa_defisiensi');
        }

        $respons = $this->ml->rekomendasiMl2([
            // Prioritas dari defisiensi lokal; seng dipetakan ke zinc sesuai kontrak ML2.
            'prioritas_gizi' => $prioritas,
            'bahan_tersedia' => $namaBahanTersedia,
            'mode_bahan' => $namaBahanTersedia === [] ? 'nonaktif' : 'sebagian',
            'top_n' => 50,
            'metode' => 'baseline',
            'kebijakan_nilai_hilang' => 'penalti',
            'bahan_dihindari' => $bahanDihindari,
        ]);

        if (! $respons['ok'] || ! is_array($respons['data'])) {
            return $this->tanpaMl2((string) ($respons['alasan'] ?? 'respons_tidak_valid'));
        }

        $data = $respons['data'];
        $versi = is_string($data['meta']['versi_model'] ?? null)
            ? $data['meta']['versi_model']
            : null;
        $hasil = is_array($data['hasil'] ?? null) ? $data['hasil'] : [];
        $jumlahDikembalikan = count($hasil);

        if ($jumlahDikembalikan === 0) {
            return $this->tanpaMl2('hasil_kosong', $versi);
        }

        $idLokal = [];
        foreach ($kandidatLokal as $kandidat) {
            $idLokal[$this->kunciResep($kandidat['resep'])] = true;
        }

        $items = [];
        $dipakai = [];
        foreach ($hasil as $item) {
            $id = (string) ($item['id_resep'] ?? '');

            if ($id === '' || ! isset($idLokal[$id]) || isset($dipakai[$id])) {
                continue;
            }

            $skor = $item['score'] ?? null;
            $items[] = [
                'id_resep' => $id,
                'skor_ml2' => is_numeric($skor) ? (float) $skor : null,
            ];
            $dipakai[$id] = true;
        }

        if ($items === []) {
            return $this->tanpaMl2('hasil_tidak_cocok', $versi, $jumlahDikembalikan);
        }

        return [
            'tersedia' => true,
            'alasan' => null,
            'versi_model' => $versi,
            'jumlah_dikembalikan' => $jumlahDikembalikan,
            'items' => $items,
        ];
    }

    /**
     * @return array{tersedia: false, alasan: string, versi_model: string|null, jumlah_dikembalikan: int, items: list<array{id_resep: string, skor_ml2: float|null}>}
     */
    private function tanpaMl2(string $alasan, ?string $versi = null, int $jumlahDikembalikan = 0): array
    {
        return [
            'tersedia' => false,
            'alasan' => $alasan,
            'versi_model' => $versi,
            'jumlah_dikembalikan' => $jumlahDikembalikan,
            'items' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $kandidatLokal
     * @param  list<array{id_resep: string, skor_ml2: float|null}>  $itemsMl2
     * @return list<array<string, mixed>>
     */
    private function pilihMenu(array $kandidatLokal, array $itemsMl2, int $topK): array
    {
        $lokalById = [];
        foreach ($kandidatLokal as $kandidat) {
            $lokalById[$this->kunciResep($kandidat['resep'])] = $kandidat;
        }

        $terpilih = [];
        $dipakai = [];

        foreach ($itemsMl2 as $item) {
            if (! isset($lokalById[$item['id_resep']]) || isset($dipakai[$item['id_resep']])) {
                continue;
            }

            $kandidat = $lokalById[$item['id_resep']];
            $kandidat['sumber_ranking'] = 'ML2';
            $kandidat['skor_ml2'] = $item['skor_ml2'];
            $terpilih[] = $kandidat;
            $dipakai[$item['id_resep']] = true;

            if (count($terpilih) >= $topK) {
                break;
            }
        }

        foreach ($kandidatLokal as $kandidat) {
            if (count($terpilih) >= $topK) {
                break;
            }

            $id = $this->kunciResep($kandidat['resep']);

            if (isset($dipakai[$id])) {
                continue;
            }

            $kandidat['sumber_ranking'] = 'LOKAL';
            $kandidat['skor_ml2'] = null;
            $terpilih[] = $kandidat;
            $dipakai[$id] = true;

            if (count($terpilih) >= $topK) {
                break;
            }
        }

        return $terpilih;
    }

    private function kunciResep(Resep $resep): string
    {
        return (string) ($resep->id_resep ?: $resep->id);
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
        bool $bolehDemo,
    ): array {
        if (! $resep->makroLengkap()) {
            return ['lolos' => false, 'modifikasi' => false];
        }

        if (! $resep->layakRekomendasi($bolehDemo)) {
            return ['lolos' => false, 'modifikasi' => false];
        }

        if ($resep->bahans->isEmpty()
            || blank($resep->cara_memasak)
            || $resep->bahans->contains(fn ($rb) => blank($rb->takaran_satuan))) {
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
