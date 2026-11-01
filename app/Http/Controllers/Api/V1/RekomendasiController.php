<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Resep;
use App\Services\RecommendationService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RekomendasiController extends Controller
{
    /**
     * SKPL A5.4.8 (F-08): Hybrid Recipe Ranking tanpa anggaran.
     */
    public function store(Request $request, string $anakId, RecommendationService $engine): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'update');

        $data = $request->validate([
            'bahan_tersedia' => ['sometimes', 'array', 'max:100'],
            'bahan_tersedia.*' => ['uuid', 'exists:bahan_makanans,id'],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:'.config('nutrition.rekomendasi.top_k_maks', 5)],
            'anggaran_max' => ['prohibited'],
            'tanggal' => ['sometimes', 'date', 'before_or_equal:today'],
        ]);

        $hasil = $engine->rekomendasikan(
            $anak,
            $data['bahan_tersedia'] ?? [],
            isset($data['top_k']) ? (int) $data['top_k'] : null,
            $data['tanggal'] ?? null,
            $request->user()->id,
        );

        AuditLogger::log($request->user(), 'rekomendasi_minta', [
            'anak_id' => $anak->id,
            'jumlah_hasil' => $hasil['jumlah_rekomendasi'],
            'metode_ranking' => $hasil['metode_ranking'],
        ]);

        return ApiResponse::success($hasil, 'Rekomendasi menu');
    }

    /**
     * SKPL A5.4.8 (F-09): detail resep dengan porsi dan gizi per porsi.
     */
    public function show(Request $request, string $resepId): JsonResponse
    {
        $resep = Resep::with('bahans.bahan')->find($resepId);

        if ($resep === null) {
            throw new HttpResponseException(ApiResponse::error(
                'RESOURCE_NOT_FOUND', 'Resep tidak ditemukan.', 404
            ));
        }

        return ApiResponse::success([
            'resep_id' => $resep->id,
            'nama_resep' => $resep->nama_resep,
            'cara_memasak' => $resep->cara_memasak,
            'kategori_gizi' => $resep->kategori_gizi,
            'jumlah_porsi_hasil' => $resep->jumlah_porsi_hasil,
            'porsi_saji' => $resep->porsi_saji,
            'estimasi_biaya' => $resep->estimasi_biaya,
            'biaya_lengkap' => $resep->biayaLengkap(),
            'gizi_per_porsi' => $resep->giziPerPorsi(),
            'usia_min_bulan' => $resep->usia_min_bulan,
            'usia_maks_bulan' => $resep->usia_maks_bulan,
            'usia_minimal_bulan_jika_dimodifikasi' => $resep->usia_minimal_bulan_jika_dimodifikasi,
            'modifikasi_untuk_turunkan_usia' => $resep->modifikasi_untuk_turunkan_usia,
            'catatan_resep' => $resep->catatan_resep,
            'alergen' => $resep->alergen ?? [],
            'keyakinan_label' => $resep->keyakinan_label,
            'jenis_menu' => $resep->jenis_menu,
            'tekstur_sajian' => $resep->tekstur_sajian,
            'sumber_resep' => $resep->sumber_resep,
            'status_verifikasi_data' => $resep->status_verifikasi_data,
            'tingkat_kesulitan' => $resep->tingkat_kesulitan,
            'bahans' => $resep->bahans->map(fn ($rb) => [
                'bahan_id' => $rb->bahan_id,
                'nama_bahan' => $rb->bahan->nama_bahan,
                'takaran_gram' => $rb->takaran_gram === null ? null : (float) $rb->takaran_gram,
                'takaran_jumlah' => $rb->takaran_jumlah,
                'takaran_satuan' => $rb->takaran_satuan,
                'takaran_gram_ekuivalen' => $rb->takaran_gram_ekuivalen,
                'catatan_bahan' => $rb->catatan_bahan,
            ])->values(),
        ], 'Detail resep');
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
