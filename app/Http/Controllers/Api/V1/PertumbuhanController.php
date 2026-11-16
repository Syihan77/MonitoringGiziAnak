<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePertumbuhanRequest;
use App\Models\Anak;
use App\Models\Pertumbuhan;
use App\Models\WhoStandard;
use App\Services\MlClient;
use App\Services\ZScoreService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class PertumbuhanController extends Controller
{
    /**
     * SKPL §5.4.5: input pengukuran + kalkulasi Z-score otomatis (201).
     */
    public function store(
        StorePertumbuhanRequest $request,
        string $anakId,
        ZScoreService $zscore,
        MlClient $ml,
    ): JsonResponse {
        $anak = $this->anakOrFail($request, $anakId, 'ukur');
        $data = $request->validated();

        $umur = ZScoreService::umurBulanPenuh(
            $anak->tanggal_lahir->toDateString(),
            $data['tanggal_ukur'],
        );

        if ($umur > 60) {
            throw new HttpResponseException(ApiResponse::error(
                'USIA_DI_LUAR_RENTANG',
                'Umur anak saat pengukuran di luar rentang 0-60 bulan.',
                400,
            ));
        }

        $hasil = $zscore->hitung(
            $anak,
            (float) $data['berat_badan_kg'],
            (float) $data['tinggi_badan_cm'],
            $data['tanggal_ukur'],
            $data['posisi_ukur'] ?? null,
        );

        $ml1 = $this->ml1Untuk(
            $anak,
            (float) $data['berat_badan_kg'],
            (float) $data['tinggi_badan_cm'],
            $hasil['umur_bulan'],
            $ml,
        );

        $catat = DB::transaction(function () use ($request, $anak, $data, $hasil) {
            $created = Pertumbuhan::create([
                'anak_id' => $anak->id,
                'tanggal_ukur' => $data['tanggal_ukur'],
                'umur_bulan' => $hasil['umur_bulan'],
                'berat_kg' => $data['berat_badan_kg'],
                'tinggi_cm' => $data['tinggi_badan_cm'],
                'tinggi_koreksi_cm' => $hasil['tinggi_koreksi_cm'],
                'posisi_ukur' => $data['posisi_ukur'] ?? null,
                'z_bb_u' => $hasil['z_bb_u'],
                'z_tb_u' => $hasil['z_tb_u'],
                'z_bb_tb' => $hasil['z_bb_tb'],
                'status_bb_u' => $hasil['status_bb_u'],
                'status_tb_u' => $hasil['status_tb_u'],
                'status_bb_tb' => $hasil['status_bb_tb'],
                'usia_bulan_saat_ukur' => $hasil['umur_bulan'],
                'kategori_pemantauan' => $hasil['kategori_pemantauan'],
                'metode_klasifikasi' => $hasil['metode_klasifikasi'],
                'versi_aturan' => $hasil['versi_aturan'],
                'status_validitas_pengukuran' => Pertumbuhan::VALID,
                'alasan_pengecualian' => null,
                'dicatat_oleh' => $request->user()->id,
            ]);

            AuditLogger::log($request->user(), 'pertumbuhan_create', [
                'anak_id' => $anak->id, 'pertumbuhan_id' => $created->id,
            ]);

            return $created;
        });

        return ApiResponse::success([
            'pertumbuhan_id' => $catat->id,
            'usia_bulan_saat_ukur' => $hasil['umur_bulan'],
            'umur_bulan' => $hasil['umur_bulan'],
            'zscore_bb_u' => $hasil['z_bb_u'],
            'zscore_tb_u' => $hasil['z_tb_u'],
            'zscore_bb_tb' => $hasil['z_bb_tb'],
            'kategori_pemantauan' => $hasil['kategori_pemantauan'],
            'metode_klasifikasi' => $hasil['metode_klasifikasi'],
            'versi_aturan' => $hasil['versi_aturan'],
            'ml1' => $ml1,
            'status_validitas_pengukuran' => Pertumbuhan::VALID,
            'pesan' => $hasil['pesan'],
            'status_rincian' => [
                'bb_u' => $hasil['status_bb_u'],
                'tb_u' => $hasil['status_tb_u'],
                'bb_tb' => $hasil['status_bb_tb'],
            ],
        ], 'Data pertumbuhan tersimpan', 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function ml1Untuk(
        Anak $anak,
        float $beratKg,
        float $tinggiCm,
        int $umurBulan,
        MlClient $ml,
    ): array {
        $tidakTersedia = fn (string $alasan): array => [
            'tersedia' => false,
            'alasan_tidak_tersedia' => $alasan,
        ];

        if (! filter_var(config('services.ml.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return $tidakTersedia('disabled');
        }

        if ($umurBulan < 12 || $umurBulan > 59) {
            return $tidakTersedia('usia_di_luar_cakupan_ml1');
        }

        if ($beratKg < 2.0 || $beratKg > 40.0 || $tinggiCm < 45.0 || $tinggiCm > 130.0) {
            return $tidakTersedia('input_di_luar_cakupan_ml1');
        }

        try {
            $hasil = $ml->prediksiMl1([
                'usia_bulan' => $umurBulan,
                'jk' => $anak->jenis_kelamin,
                'bb_kg' => $beratKg,
                'tb_cm' => $tinggiCm,
            ]);
        } catch (Throwable) {
            return $tidakTersedia('kesalahan_tidak_terduga');
        }

        if (! $hasil['ok'] || ! is_array($hasil['data'])) {
            return $tidakTersedia((string) ($hasil['alasan'] ?? 'respons_tidak_valid'));
        }

        return ['tersedia' => true] + $hasil['data'];
    }

    /**
     * SKPL §5.4.5: riwayat pengukuran (untuk grafik tren).
     */
    public function index(Request $request, string $anakId): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'view');

        $filter = $request->validate([
            'dari_tanggal' => ['sometimes', 'date'],
            'sampai_tanggal' => ['sometimes', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $riwayat = Pertumbuhan::query()
            ->where('anak_id', $anak->id)
            ->when(isset($filter['dari_tanggal']), fn ($q) => $q->whereDate('tanggal_ukur', '>=', $filter['dari_tanggal']))
            ->when(isset($filter['sampai_tanggal']), fn ($q) => $q->whereDate('tanggal_ukur', '<=', $filter['sampai_tanggal']))
            ->orderBy('tanggal_ukur')
            ->when(isset($filter['limit']), fn ($q) => $q->limit($filter['limit']))
            ->get()
            ->map(fn (Pertumbuhan $p) => [
                'pertumbuhan_id' => $p->id,
                'tanggal_ukur' => $p->tanggal_ukur->toDateString(),
                'usia_bulan_saat_ukur' => $p->usia_bulan_saat_ukur === null
                    ? $p->umur_bulan
                    : (float) $p->usia_bulan_saat_ukur,
                'umur_bulan' => $p->umur_bulan,
                'berat_kg' => (float) $p->berat_kg,
                'tinggi_cm' => (float) $p->tinggi_cm,
                'zscore_bb_u' => $p->z_bb_u === null ? null : (float) $p->z_bb_u,
                'zscore_tb_u' => $p->z_tb_u === null ? null : (float) $p->z_tb_u,
                'zscore_bb_tb' => $p->z_bb_tb === null ? null : (float) $p->z_bb_tb,
                'kategori_pemantauan' => $p->kategoriEfektif(),
                'metode_klasifikasi' => $p->metodeKlasifikasiEfektif(),
                'versi_aturan' => $p->versiAturanEfektif(),
                'status_validitas_pengukuran' => $p->statusValiditasEfektif(),
                'pesan' => $p->pesanEfektif(),
            ]);

        return ApiResponse::success($riwayat, 'Riwayat pertumbuhan');
    }

    /**
     * Grafik pertumbuhan per anak + garis acuan Permenkes/WHO.
     */
    public function tren(Request $request, string $anakId): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'view');

        $filter = $request->validate([
            'dari_tanggal' => ['sometimes', 'date'],
            'sampai_tanggal' => ['sometimes', 'date'],
        ]);

        $titik = Pertumbuhan::query()
            ->where('anak_id', $anak->id)
            ->when(isset($filter['dari_tanggal']), fn ($q) => $q->whereDate('tanggal_ukur', '>=', $filter['dari_tanggal']))
            ->when(isset($filter['sampai_tanggal']), fn ($q) => $q->whereDate('tanggal_ukur', '<=', $filter['sampai_tanggal']))
            ->orderBy('tanggal_ukur')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Pertumbuhan $p) => $this->barisTitik($p))
            ->values();

        return ApiResponse::success([
            'anak_id' => $anak->id,
            'nama' => $anak->nama,
            'jenis_kelamin' => $anak->jenis_kelamin,
            'tanggal_lahir' => $anak->tanggal_lahir->toDateString(),
            'titik' => $titik,
            'acuan' => $this->acuanPertumbuhan($anak->jenis_kelamin),
        ], 'Grafik pertumbuhan anak');
    }

    /**
     * @return array<string, mixed>
     */
    private function barisTitik(Pertumbuhan $p): array
    {
        return [
            'pertumbuhan_id' => $p->id,
            'tanggal_ukur' => $p->tanggal_ukur->toDateString(),
            'usia_bulan_saat_ukur' => $p->usia_bulan_saat_ukur === null
                ? $p->umur_bulan
                : (float) $p->usia_bulan_saat_ukur,
            'umur_bulan' => $p->umur_bulan,
            'berat_kg' => (float) $p->berat_kg,
            'tinggi_cm' => (float) $p->tinggi_cm,
            'zscore_bb_u' => $p->z_bb_u === null ? null : (float) $p->z_bb_u,
            'zscore_tb_u' => $p->z_tb_u === null ? null : (float) $p->z_tb_u,
            'zscore_bb_tb' => $p->z_bb_tb === null ? null : (float) $p->z_bb_tb,
            'kategori_pemantauan' => $p->kategoriEfektif(),
            'status_validitas_pengukuran' => $p->statusValiditasEfektif(),
            'pesan' => $p->pesanEfektif(),
        ];
    }

    /**
     * @return array{sumber: string, versi_aturan: string, bb_u: list<array<string, int|float>>, tb_u: list<array<string, int|float>>}
     */
    private function acuanPertumbuhan(string $jenisKelamin): array
    {
        $rows = WhoStandard::query()
            ->where('jenis_kelamin', $jenisKelamin)
            ->where('dasar', 'umur')
            ->whereIn('indikator', [WhoStandard::BB_U, WhoStandard::TB_U])
            ->orderBy('umur_bulan')
            ->get();

        $bbU = $rows->where('indikator', WhoStandard::BB_U)
            ->map(fn (WhoStandard $row) => $this->barisAcuan($row))
            ->values()
            ->all();

        $tbU = [];
        foreach ($rows->where('indikator', WhoStandard::TB_U) as $row) {
            $umur = (int) $row->umur_bulan;
            $varian = $umur < 24 ? 'pb' : 'tb';

            if ($row->varian === $varian) {
                $tbU[$umur] = $this->barisAcuan($row);
            }
        }
        ksort($tbU);

        return [
            'sumber' => 'Permenkes No. 2/2020 / WHO',
            'versi_aturan' => ZScoreService::versiAturan(),
            'bb_u' => $bbU,
            'tb_u' => array_values($tbU),
        ];
    }

    /**
     * @return array{usia_bulan: int, sd_minus3: float, sd_minus2: float, sd_minus1: float, median: float, sd_plus1: float, sd_plus2: float, sd_plus3: float}
     */
    private function barisAcuan(WhoStandard $row): array
    {
        return [
            'usia_bulan' => (int) $row->umur_bulan,
            'sd_minus3' => (float) $row->sd_minus3,
            'sd_minus2' => (float) $row->sd_minus2,
            'sd_minus1' => (float) $row->sd_minus1,
            'median' => (float) $row->median,
            'sd_plus1' => (float) $row->sd_plus1,
            'sd_plus2' => (float) $row->sd_plus2,
            'sd_plus3' => (float) $row->sd_plus3,
        ];
    }

    private function anakOrFail(Request $request, string $anakId, string $ability): Anak
    {
        $anak = Anak::with('user')->find($anakId);

        if ($anak === null) {
            throw new HttpResponseException(ApiResponse::error('RESOURCE_NOT_FOUND', 'Data anak tidak ditemukan.', 404));
        }

        Gate::authorize($ability, $anak);

        return $anak;
    }
}
