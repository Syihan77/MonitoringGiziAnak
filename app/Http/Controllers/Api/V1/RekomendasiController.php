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
     * SKPL §5.4.7 (F-08): rekomendasi menu berbasis bahan/anggaran.
     */
    public function store(Request $request, string $anakId, RecommendationService $engine): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'update');

        $data = $request->validate([
            'bahan_tersedia' => ['sometimes', 'array', 'max:100'],
            'bahan_tersedia.*' => ['uuid', 'exists:bahan_makanans,id'],
            'anggaran_max' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'tanggal' => ['sometimes', 'date', 'before_or_equal:today'],
        ]);

        if (empty($data['bahan_tersedia'] ?? []) && ! isset($data['anggaran_max'])) {
            throw new HttpResponseException(ApiResponse::error(
                'VALIDATION_ERROR',
                'Data yang dikirim tidak valid.',
                400,
                ['errors' => ['masukan' => ['Isi bahan_tersedia atau anggaran_max.']]]
            ));
        }

        $hasil = $engine->rekomendasikan(
            $anak,
            $data['bahan_tersedia'] ?? [],
            isset($data['anggaran_max']) ? (float) $data['anggaran_max'] : null,
            $data['tanggal'] ?? null,
            $request->user()->id,
        );

        AuditLogger::log($request->user(), 'rekomendasi_minta', [
            'anak_id' => $anak->id, 'jumlah_hasil' => $hasil['jumlah_rekomendasi'],
        ]);

        return ApiResponse::success($hasil, 'Rekomendasi menu', 201);
    }

    /**
     * SKPL §5.4.7 (F-09): detail resep beserta bahan & takaran.
     */
    public function show(Request $request, string $resepId): JsonResponse
    {
        $resep = Resep::with('bahans.bahan')->find($resepId);

        if ($resep === null) {
            throw new HttpResponseException(ApiResponse::error(
                'RESOURCE_NOT_FOUND', 'Resep tidak ditemukan.', 404
            ));
        }

        $gizi = ['energi' => 0.0, 'protein' => 0.0, 'lemak' => 0.0, 'karbohidrat' => 0.0];
        foreach ($resep->bahans as $rb) {
            $faktor = ((float) $rb->takaran_gram) / 100.0;
            $gizi['energi'] += ((float) ($rb->bahan->energi_per100g ?? 0.0)) * $faktor;
            $gizi['protein'] += ((float) ($rb->bahan->protein_per100g ?? 0.0)) * $faktor;
            $gizi['lemak'] += ((float) ($rb->bahan->lemak_per100g ?? 0.0)) * $faktor;
            $gizi['karbohidrat'] += ((float) ($rb->bahan->karbohidrat_per100g ?? 0.0)) * $faktor;
        }
        $porsi = max($resep->porsi_saji, 1);
        $giziPerPorsi = array_map(fn ($v) => round($v / $porsi, 1), $gizi);

        return ApiResponse::success([
            'resep_id' => $resep->id,
            'nama_resep' => $resep->nama_resep,
            'cara_memasak' => $resep->cara_memasak,
            'kategori_gizi' => $resep->kategori_gizi,
            'estimasi_biaya' => $resep->estimasi_biaya === null ? null : (float) $resep->estimasi_biaya,
            'biaya_lengkap' => $resep->biayaLengkap(),
            'porsi_saji' => $resep->porsi_saji,
            'tingkat_kesulitan' => $resep->tingkat_kesulitan,
            'gizi_per_porsi' => $giziPerPorsi,
            'bahans' => $resep->bahans->map(fn ($rb) => [
                'bahan_id' => $rb->bahan_id,
                'nama_bahan' => $rb->bahan->nama_bahan,
                'takaran_gram' => (float) $rb->takaran_gram,
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
