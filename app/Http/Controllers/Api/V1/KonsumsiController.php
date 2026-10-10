<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Konsumsi;
use App\Services\NutritionService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class KonsumsiController extends Controller
{
    /**
     * SKPL §5.4.6 (F-06): catat konsumsi harian multi-item.
     * Porsi disimpan gram; input kg dikonversi (dropdown kg/gram di klien).
     */
    public function store(Request $request, string $anakId): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'update');

        $data = $request->validate([
            'tanggal_konsumsi' => ['required', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.bahan_id' => ['required', 'uuid', 'exists:bahan_makanans,id'],
            'items.*.porsi_gram' => ['required', 'numeric', 'min:0.1', 'max:5000'],
            'items.*.satuan' => ['sometimes', 'in:gram,kg'],
        ]);

        $ids = DB::transaction(function () use ($request, $anak, $data) {
            $dibuat = [];

            foreach ($data['items'] as $item) {
                $gram = (float) $item['porsi_gram'];

                if (($item['satuan'] ?? 'gram') === 'kg') {
                    $gram *= 1000.0;
                }

                $dibuat[] = Konsumsi::create([
                    'anak_id' => $anak->id,
                    'bahan_id' => $item['bahan_id'],
                    'porsi_gram' => round($gram, 2),
                    'tanggal_konsumsi' => $data['tanggal_konsumsi'],
                    'dicatat_oleh' => $request->user()->id,
                ])->id;
            }

            AuditLogger::log($request->user(), 'konsumsi_create', [
                'anak_id' => $anak->id,
                'tanggal_konsumsi' => $data['tanggal_konsumsi'],
                'jumlah_item' => count($dibuat),
            ]);

            return $dibuat;
        });

        return ApiResponse::success([
            'tanggal_konsumsi' => $data['tanggal_konsumsi'],
            'jumlah_item' => count($ids),
            'konsumsi_ids' => $ids,
        ], 'Konsumsi harian tersimpan', 201);
    }

    /**
     * SKPL §5.4.6: daftar konsumsi pada tanggal tertentu,
     * atau rentang tanggal (untuk grid bulanan).
     */
    public function index(Request $request, string $anakId): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'view');

        $filter = $request->validate([
            'tanggal' => ['sometimes', 'date', 'prohibits:dari_tanggal,sampai_tanggal'],
            'dari_tanggal' => ['sometimes', 'date', 'required_with:sampai_tanggal'],
            'sampai_tanggal' => ['sometimes', 'date', 'required_with:dari_tanggal', 'after_or_equal:dari_tanggal'],
        ]);

        $items = Konsumsi::query()
            ->with('bahan')
            ->where('anak_id', $anak->id)
            ->when(isset($filter['tanggal']), fn ($q) => $q->where('tanggal_konsumsi', $filter['tanggal']))
            ->when(isset($filter['dari_tanggal']), fn ($q) => $q->where('tanggal_konsumsi', '>=', $filter['dari_tanggal']))
            ->when(isset($filter['sampai_tanggal']), fn ($q) => $q->where('tanggal_konsumsi', '<=', $filter['sampai_tanggal']))
            ->orderBy('tanggal_konsumsi')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Konsumsi $k) => [
                'konsumsi_id' => $k->id,
                'bahan_id' => $k->bahan_id,
                'nama_bahan' => $k->bahan->nama_bahan,
                'porsi_gram' => (float) $k->porsi_gram,
                'tanggal_konsumsi' => $k->tanggal_konsumsi->toDateString(),
            ]);

        return ApiResponse::success($items, 'Daftar konsumsi harian');
    }

    /**
     * SKPL §5.4.6 (F-07): analisis kecukupan gizi.
     */
    public function analisis(Request $request, string $anakId, NutritionService $gizi): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'view');

        $filter = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
        ]);

        return ApiResponse::success(
            $gizi->analisis($anak, $filter['tanggal']),
            'Analisis kecukupan gizi'
        );
    }

    /**
     * Agregat pemenuhan gizi bulanan (ringkasan bulan terpenuhi/belum).
     */
    public function bulanan(Request $request, string $anakId, NutritionService $gizi): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'view');

        $filter = $request->validate([
            'bulan' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        return ApiResponse::success(
            $gizi->agregatBulanan($anak, $filter['bulan']),
            'Agregat gizi bulanan'
        );
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
