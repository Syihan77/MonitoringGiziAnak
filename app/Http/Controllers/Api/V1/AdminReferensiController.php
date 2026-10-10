<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BahanMakanan;
use App\Models\Resep;
use App\Models\ResepBahan;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReferensiController extends Controller
{
    private function wajibAdmin(Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            throw new HttpResponseException(ApiResponse::error(
                'FORBIDDEN_RESOURCE', 'Hanya admin yang dapat mengelola data referensi.', 403
            ));
        }
    }

    // ---- Bahan makanan (SKPL §5.4.9, F-11) ----

    public function bahanIndex(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = BahanMakanan::query()
            ->when(isset($filter['q']), fn ($q) => $q->where('nama_bahan', 'ilike', '%'.$filter['q'].'%'))
            ->orderBy('nama_bahan')
            ->paginate($filter['limit'] ?? 20);

        return ApiResponse::success($page, 'Daftar bahan makanan');
    }

    public function bahanStore(Request $request): JsonResponse
    {
        $this->wajibAdmin($request);
        $data = $request->validate($this->aturanBahan());

        $bahan = DB::transaction(function () use ($request, $data) {
            $dibuat = BahanMakanan::create($data);
            AuditLogger::log($request->user(), 'bahan_create', ['bahan_id' => $dibuat->id]);

            return $dibuat;
        });

        return ApiResponse::success($bahan->refresh(), 'Bahan makanan tersimpan', 201);
    }

    public function bahanUpdate(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $bahan = $this->bahanOrFail($id);

        $data = $request->validate(array_map(
            fn ($r) => ['sometimes', ...$r],
            $this->aturanBahan()
        ));

        DB::transaction(function () use ($request, $bahan, $data, $id) {
            $bahan->update($data);
            AuditLogger::log($request->user(), 'bahan_update', ['bahan_id' => $id]);
        });

        $this->hitungUlangResepTerkait($bahan->id);

        return ApiResponse::success($bahan->refresh(), 'Bahan makanan diperbarui');
    }

    public function bahanDestroy(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $bahan = $this->bahanOrFail($id);

        if (ResepBahan::where('bahan_id', $id)->exists() || \App\Models\Konsumsi::where('bahan_id', $id)->exists()) {
            throw new HttpResponseException(ApiResponse::error(
                'VALIDATION_ERROR', 'Bahan masih dipakai resep/konsumsi dan tidak dapat dihapus.', 400
            ));
        }

        DB::transaction(function () use ($request, $bahan, $id) {
            $bahan->delete();
            AuditLogger::log($request->user(), 'bahan_delete', ['bahan_id' => $id]);
        });

        return ApiResponse::success([], 'Bahan makanan dihapus');
    }

    // ---- Resep (SKPL §5.4.9, F-12) ----

    public function resepIndex(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'kategori_gizi' => ['sometimes', 'string', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Resep::query()
            ->when(isset($filter['kategori_gizi']), fn ($q) => $q->where('kategori_gizi', $filter['kategori_gizi']))
            ->orderBy('nama_resep')
            ->paginate($filter['limit'] ?? 20);

        return ApiResponse::success($page, 'Daftar resep');
    }

    public function resepStore(Request $request): JsonResponse
    {
        $this->wajibAdmin($request);
        $data = $request->validate($this->aturanResep());

        $resep = DB::transaction(function () use ($request, $data) {
            $dibuat = Resep::create([
                'nama_resep' => $data['nama_resep'],
                'cara_memasak' => $data['cara_memasak'],
                'kategori_gizi' => $data['kategori_gizi'],
                'porsi_saji' => $data['porsi_saji'],
                'tingkat_kesulitan' => $data['tingkat_kesulitan'] ?? null,
                'estimasi_biaya' => null,
            ]);

            foreach ($data['bahans'] as $item) {
                $dibuat->bahans()->create([
                    'bahan_id' => $item['bahan_id'],
                    'takaran_gram' => $item['takaran_gram'],
                ]);
            }

            $dibuat->update(['estimasi_biaya' => $dibuat->hitungUlangBiaya()]);
            AuditLogger::log($request->user(), 'resep_create', ['resep_id' => $dibuat->id]);

            return $dibuat;
        });

        return ApiResponse::success($resep->load('bahans.bahan'), 'Resep tersimpan', 201);
    }

    public function resepUpdate(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $resep = $this->resepOrFail($id);

        $data = $request->validate([
            'nama_resep' => ['sometimes', 'required', 'string', 'max:100'],
            'cara_memasak' => ['sometimes', 'required', 'string'],
            'kategori_gizi' => ['sometimes', 'required', 'string', 'max:50'],
            'porsi_saji' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'tingkat_kesulitan' => ['sometimes', 'nullable', 'in:Mudah,Sedang,Sulit'],
            'bahans' => ['sometimes', 'required', 'array', 'min:1', 'max:50'],
            'bahans.*.bahan_id' => ['required', 'uuid', 'exists:bahan_makanans,id'],
            'bahans.*.takaran_gram' => ['required', 'numeric', 'min:0.1', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $resep, $data, $id) {
            $resep->update(collect($data)->except('bahans')->all());

            if (isset($data['bahans'])) {
                $resep->bahans()->delete();

                foreach ($data['bahans'] as $item) {
                    $resep->bahans()->create([
                        'bahan_id' => $item['bahan_id'],
                        'takaran_gram' => $item['takaran_gram'],
                    ]);
                }

                $resep->update(['estimasi_biaya' => $resep->hitungUlangBiaya()]);
            }

            AuditLogger::log($request->user(), 'resep_update', ['resep_id' => $id]);
        });

        return ApiResponse::success($resep->refresh()->load('bahans.bahan'), 'Resep diperbarui');
    }

    public function resepDestroy(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $resep = $this->resepOrFail($id);

        DB::transaction(function () use ($request, $resep, $id) {
            $resep->delete(); // resep_bahans ikut cascade
            AuditLogger::log($request->user(), 'resep_delete', ['resep_id' => $id]);
        });

        return ApiResponse::success([], 'Resep dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function aturanBahan(): array
    {
        return [
            'nama_bahan' => ['required', 'string', 'max:100', 'unique:bahan_makanans,nama_bahan'],
            'kelompok_gizi' => ['required', 'string', 'max:50'],
            'energi_per100g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'karbohidrat_per100g' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'protein_per100g' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lemak_per100g' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'zat_besi_per100g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'zinc_per100g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'estimasi_harga_per100g' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'sumber_rujukan' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aturanResep(): array
    {
        return [
            'nama_resep' => ['required', 'string', 'max:100'],
            'cara_memasak' => ['required', 'string'],
            'kategori_gizi' => ['required', 'string', 'max:50'],
            'porsi_saji' => ['required', 'integer', 'min:1', 'max:100'],
            'tingkat_kesulitan' => ['nullable', 'in:Mudah,Sedang,Sulit'],
            'bahans' => ['required', 'array', 'min:1', 'max:50'],
            'bahans.*.bahan_id' => ['required', 'uuid', 'exists:bahan_makanans,id'],
            'bahans.*.takaran_gram' => ['required', 'numeric', 'min:0.1', 'max:5000'],
        ];
    }

    private function bahanOrFail(string $id): BahanMakanan
    {
        $bahan = BahanMakanan::find($id);

        if ($bahan === null) {
            throw new HttpResponseException(ApiResponse::error(
                'RESOURCE_NOT_FOUND', 'Bahan makanan tidak ditemukan.', 404
            ));
        }

        return $bahan;
    }

    private function resepOrFail(string $id): Resep
    {
        $resep = Resep::find($id);

        if ($resep === null) {
            throw new HttpResponseException(ApiResponse::error(
                'RESOURCE_NOT_FOUND', 'Resep tidak ditemukan.', 404
            ));
        }

        return $resep;
    }

    private function hitungUlangResepTerkait(string $bahanId): void
    {
        $resepIds = ResepBahan::where('bahan_id', $bahanId)->pluck('resep_id')->unique();

        foreach ($resepIds as $resepId) {
            $resep = Resep::find($resepId);

            if ($resep !== null) {
                $resep->update(['estimasi_biaya' => $resep->hitungUlangBiaya()]);
            }
        }
    }
}
