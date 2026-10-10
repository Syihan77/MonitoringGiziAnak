<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePertumbuhanRequest;
use App\Models\Anak;
use App\Models\Pertumbuhan;
use App\Services\ZScoreService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PertumbuhanController extends Controller
{
    /**
     * SKPL §5.4.5: input pengukuran + kalkulasi Z-score otomatis (201).
     */
    public function store(StorePertumbuhanRequest $request, string $anakId, ZScoreService $zscore): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'view');
        $data = $request->validated();

        $hasil = $zscore->hitung(
            $anak,
            (float) $data['berat_badan_kg'],
            (float) $data['tinggi_badan_cm'],
            $data['tanggal_ukur'],
            $data['posisi_ukur'] ?? null,
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
                'status_gizi_antropometri' => $hasil['status_gizi_antropometri'],
                'dicatat_oleh' => $request->user()->id,
            ]);

            AuditLogger::log($request->user(), 'pertumbuhan_create', [
                'anak_id' => $anak->id, 'pertumbuhan_id' => $created->id,
            ]);

            return $created;
        });

        return ApiResponse::success([
            'pertumbuhan_id' => $catat->id,
            'umur_bulan' => $hasil['umur_bulan'],
            'zscore_bb_u' => $hasil['z_bb_u'],
            'zscore_tb_u' => $hasil['z_tb_u'],
            'zscore_bb_tb' => $hasil['z_bb_tb'],
            'status_gizi_antropometri' => $hasil['status_gizi_antropometri'],
            'status_rincian' => [
                'bb_u' => $hasil['status_bb_u'],
                'tb_u' => $hasil['status_tb_u'],
                'bb_tb' => $hasil['status_bb_tb'],
            ],
        ], 'Data pertumbuhan tersimpan', 201);
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
            ->when(isset($filter['dari_tanggal']), fn ($q) => $q->where('tanggal_ukur', '>=', $filter['dari_tanggal']))
            ->when(isset($filter['sampai_tanggal']), fn ($q) => $q->where('tanggal_ukur', '<=', $filter['sampai_tanggal']))
            ->orderBy('tanggal_ukur')
            ->when(isset($filter['limit']), fn ($q) => $q->limit($filter['limit']))
            ->get()
            ->map(fn (Pertumbuhan $p) => [
                'pertumbuhan_id' => $p->id,
                'tanggal_ukur' => $p->tanggal_ukur->toDateString(),
                'umur_bulan' => $p->umur_bulan,
                'berat_kg' => (float) $p->berat_kg,
                'tinggi_cm' => (float) $p->tinggi_cm,
                'zscore_bb_u' => (float) $p->z_bb_u,
                'zscore_tb_u' => (float) $p->z_tb_u,
                'zscore_bb_tb' => (float) $p->z_bb_tb,
                'status_gizi_antropometri' => $p->status_gizi_antropometri,
            ]);

        return ApiResponse::success($riwayat, 'Riwayat pertumbuhan');
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
