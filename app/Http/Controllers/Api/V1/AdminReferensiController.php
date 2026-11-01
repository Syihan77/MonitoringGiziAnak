<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BahanMakanan;
use App\Models\Konsumsi;
use App\Models\Resep;
use App\Models\ResepBahan;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\TakaranConverter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function bahanIndex(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = BahanMakanan::query()
            ->when(isset($filter['q']), fn ($q) => $q->whereRaw(
                'LOWER(nama_bahan) LIKE ?',
                ['%'.mb_strtolower($filter['q']).'%']
            ))
            ->orderBy('nama_bahan')
            ->paginate($filter['limit'] ?? 20);

        $page->getCollection()->transform(fn (BahanMakanan $bahan) => $this->bahanData($bahan));

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

        return ApiResponse::success($this->bahanData($bahan->refresh()), 'Bahan makanan tersimpan', 201);
    }

    public function bahanUpdate(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $bahan = $this->bahanOrFail($id);

        $aturan = collect($this->aturanBahan())
            ->map(fn ($rules) => ['sometimes', ...$rules])
            ->all();
        $aturan['nama_bahan'] = [
            'sometimes', 'required', 'string', 'max:100',
            Rule::unique('bahan_makanans', 'nama_bahan')->ignore($bahan->id),
        ];

        $data = $request->validate($aturan);

        DB::transaction(function () use ($request, $bahan, $data, $id) {
            $bahan->update($data);
            AuditLogger::log($request->user(), 'bahan_update', ['bahan_id' => $id]);
        });

        return ApiResponse::success($this->bahanData($bahan->refresh()), 'Bahan makanan diperbarui');
    }

    public function bahanDestroy(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $bahan = $this->bahanOrFail($id);

        if (ResepBahan::where('bahan_id', $id)->exists() || Konsumsi::where('bahan_id', $id)->exists()) {
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

    public function resepIndex(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'kategori_gizi' => ['sometimes', 'string', 'max:50'],
            'status_verifikasi_data' => ['sometimes', 'in:DATA_LENGKAP,TERVERIFIKASI,PERLU_VERIFIKASI'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Resep::query()
            ->withCount('bahans')
            ->when(isset($filter['q']), fn ($q) => $q->whereRaw(
                'LOWER(nama_resep) LIKE ?',
                ['%'.mb_strtolower($filter['q']).'%']
            ))
            ->when(isset($filter['kategori_gizi']), fn ($q) => $q->where('kategori_gizi', $filter['kategori_gizi']))
            ->when(isset($filter['status_verifikasi_data']), fn ($q) => $q->where('status_verifikasi_data', $filter['status_verifikasi_data']))
            ->orderBy('nama_resep')
            ->paginate($filter['limit'] ?? 20);

        $page->getCollection()->transform(fn (Resep $resep) => $this->resepData($resep));

        return ApiResponse::success($page, 'Daftar resep');
    }

    public function resepStore(Request $request): JsonResponse
    {
        $this->wajibAdmin($request);
        $data = $request->validate($this->aturanResep());

        $resep = DB::transaction(function () use ($request, $data) {
            $porsi = $data['jumlah_porsi_hasil'] ?? $data['porsi_saji'] ?? 1;
            $dibuat = Resep::create([
                ...$this->atributResep($data),
                'porsi_saji' => $porsi,
                'jumlah_porsi_hasil' => $porsi,
                'kategori_gizi' => $data['kategori_gizi'] ?? 'umum',
                'status_verifikasi_data' => $data['status_verifikasi_data'] ?? Resep::VERIFIKASI_PERLU_VERIFIKASI,
                'estimasi_biaya' => null,
            ]);

            $this->simpanBahan($dibuat, $data['bahans']);
            $this->pastikanVerifikasiLengkap($dibuat->refresh());
            AuditLogger::log($request->user(), 'resep_create', ['resep_id' => $dibuat->id]);

            return $dibuat;
        });

        return ApiResponse::success(
            $this->resepData($resep->load('bahans.bahan'), true),
            'Resep tersimpan',
            201,
        );
    }

    public function resepUpdate(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $resep = $this->resepOrFail($id);
        $data = $request->validate($this->aturanResep($resep->id));

        DB::transaction(function () use ($request, $resep, $data, $id) {
            $atribut = collect($data)->except(['bahans'])->all();

            if (array_key_exists('jumlah_porsi_hasil', $atribut)) {
                $atribut['porsi_saji'] = $atribut['jumlah_porsi_hasil'];
            } elseif (array_key_exists('porsi_saji', $atribut)) {
                $atribut['jumlah_porsi_hasil'] = $atribut['porsi_saji'];
            }

            $resep->update($atribut);

            if (isset($data['bahans'])) {
                $resep->bahans()->delete();
                $this->simpanBahan($resep, $data['bahans']);
            }

            $this->pastikanVerifikasiLengkap($resep->refresh());
            AuditLogger::log($request->user(), 'resep_update', ['resep_id' => $id]);
        });

        return ApiResponse::success(
            $this->resepData($resep->refresh()->load('bahans.bahan'), true),
            'Resep diperbarui',
        );
    }

    public function resepDestroy(Request $request, string $id): JsonResponse
    {
        $this->wajibAdmin($request);
        $resep = $this->resepOrFail($id);

        DB::transaction(function () use ($request, $resep, $id) {
            $resep->delete();
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
            'estimasi_harga_per100g' => ['prohibited'],
            'sumber_rujukan' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aturanResep(?string $id = null): array
    {
        $parsial = $id === null ? [] : ['sometimes'];

        return [
            'nama_resep' => [
                ...$parsial, 'required', 'string', 'max:100',
                $id === null
                    ? 'unique:reseps,nama_resep'
                    : Rule::unique('reseps', 'nama_resep')->ignore($id),
            ],
            'cara_memasak' => [...$parsial, 'required', 'string'],
            'kategori_gizi' => ['nullable', 'string', 'max:50'],
            'porsi_saji' => ['nullable', 'integer', 'min:1', 'max:100'],
            'jumlah_porsi_hasil' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tingkat_kesulitan' => ['nullable', 'in:Mudah,Sedang,Sulit'],
            'energi_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'protein_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'lemak_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'karbohidrat_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'serat_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'kalsium_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'zat_besi_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'zinc_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'vitamin_a_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'vitamin_c_per_porsi' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'usia_min_bulan' => ['nullable', 'integer', 'min:0', 'max:60'],
            'usia_maks_bulan' => ['nullable', 'integer', 'min:0', 'max:60', 'gte:usia_min_bulan'],
            'usia_minimal_bulan_jika_dimodifikasi' => ['nullable', 'integer', 'min:0', 'max:60', 'lte:usia_min_bulan'],
            'modifikasi_untuk_turunkan_usia' => ['nullable', 'string', 'max:200'],
            'keyakinan_label' => ['nullable', 'in:tinggi,sedang,rendah'],
            'jenis_menu' => ['nullable', 'string', 'max:50'],
            'alergen' => ['nullable', 'array', 'max:20'],
            'alergen.*' => ['string', 'max:50'],
            'catatan_resep' => ['nullable', 'string'],
            'tekstur_sajian' => ['nullable', 'string', 'max:50'],
            'sumber_resep' => ['nullable', 'string', 'max:200'],
            'status_verifikasi_data' => ['nullable', 'in:DATA_LENGKAP,TERVERIFIKASI,PERLU_VERIFIKASI'],
            'bahans' => [...$parsial, 'required', 'array', 'min:1', 'max:50'],
            'bahans.*.bahan_id' => ['required', 'uuid', 'exists:bahan_makanans,id'],
            'bahans.*.takaran_gram' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'bahans.*.takaran_jumlah' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'bahans.*.takaran_satuan' => ['nullable', 'string', 'max:20'],
            'bahans.*.takaran_gram_ekuivalen' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'bahans.*.catatan_bahan' => ['nullable', 'string', 'max:200'],
            'estimasi_biaya' => ['prohibited'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function atributResep(array $data): array
    {
        return collect($data)->only([
            'nama_resep',
            'cara_memasak',
            'kategori_gizi',
            'tingkat_kesulitan',
            'energi_per_porsi',
            'protein_per_porsi',
            'lemak_per_porsi',
            'karbohidrat_per_porsi',
            'serat_per_porsi',
            'kalsium_per_porsi',
            'zat_besi_per_porsi',
            'zinc_per_porsi',
            'vitamin_a_per_porsi',
            'vitamin_c_per_porsi',
            'usia_min_bulan',
            'usia_maks_bulan',
            'usia_minimal_bulan_jika_dimodifikasi',
            'modifikasi_untuk_turunkan_usia',
            'keyakinan_label',
            'jenis_menu',
            'alergen',
            'catatan_resep',
            'tekstur_sajian',
            'sumber_resep',
            'status_verifikasi_data',
        ])->all();
    }

    /**
     * @param  list<array<string, mixed>>  $bahans
     */
    private function simpanBahan(Resep $resep, array $bahans): void
    {
        foreach ($bahans as $index => $item) {
            $gram = $item['takaran_gram_ekuivalen'] ?? $item['takaran_gram'] ?? null;

            if ($gram === null && ($item['takaran_satuan'] ?? null) === 'kg') {
                $gram = ((float) ($item['takaran_jumlah'] ?? 0)) * 1000;
            }

            if ($gram === null) {
                $bahan = BahanMakanan::find($item['bahan_id']);
                if ($bahan === null || ! TakaranConverter::pelengkap($bahan->nama_bahan)) {
                    throw ValidationException::withMessages([
                        "bahans.{$index}.takaran_gram_ekuivalen" => 'Takaran gram atau ekuivalen gram wajib diisi.',
                    ]);
                }
            }

            if ($gram !== null && (float) $gram <= 0) {
                throw ValidationException::withMessages([
                    "bahans.{$index}.takaran_gram_ekuivalen" => 'Takaran gram atau ekuivalen gram wajib diisi.',
                ]);
            }

            $resep->bahans()->create([
                'bahan_id' => $item['bahan_id'],
                'takaran_gram' => $gram,
                'takaran_jumlah' => $item['takaran_jumlah'] ?? $gram,
                'takaran_satuan' => $item['takaran_satuan'] ?? 'g',
                'takaran_gram_ekuivalen' => $gram,
                'catatan_bahan' => $item['catatan_bahan'] ?? ($gram === null ? 'Pelengkap tanpa takaran' : null),
            ]);
        }
    }

    private function pastikanVerifikasiLengkap(Resep $resep): void
    {
        if (! in_array($resep->status_verifikasi_data, [
            Resep::VERIFIKASI_DATA_LENGKAP,
            Resep::VERIFIKASI_TERVERIFIKASI,
        ], true)) {
            return;
        }

        $resep->loadMissing('bahans');
        $galat = [];

        if ($resep->jumlah_porsi_hasil === null || (int) $resep->jumlah_porsi_hasil < 1) {
            $galat['jumlah_porsi_hasil'] = 'Jumlah porsi hasil wajib diisi untuk resep data lengkap.';
        }

        if ($resep->usia_min_bulan === null) {
            $galat['usia_min_bulan'] = 'Usia minimal wajib diisi untuk resep data lengkap.';
        }

        if ($resep->alergen === null) {
            $galat['alergen'] = 'Status alergen wajib diisi untuk resep data lengkap.';
        }

        foreach ($resep->giziPerPorsi() as $nama => $nilai) {
            if ($nilai === null) {
                $galat['gizi_per_porsi'] = "Nilai gizi per porsi ({$nama}) belum lengkap.";
            }
        }

        if ($resep->bahans->isEmpty()) {
            $galat['bahans'] = 'Resep data lengkap wajib memiliki bahan.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function bahanData(BahanMakanan $bahan): array
    {
        return [
            'id' => $bahan->id,
            'bahan_id' => $bahan->id,
            'nama_bahan' => $bahan->nama_bahan,
            'kelompok_gizi' => $bahan->kelompok_gizi,
            'energi_per100g' => $bahan->energi_per100g,
            'karbohidrat_per100g' => $bahan->karbohidrat_per100g,
            'protein_per100g' => $bahan->protein_per100g,
            'lemak_per100g' => $bahan->lemak_per100g,
            'zat_besi_per100g' => $bahan->zat_besi_per100g,
            'zinc_per100g' => $bahan->zinc_per100g,
            'sumber_rujukan' => $bahan->sumber_rujukan,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resepData(Resep $resep, bool $denganBahan = false): array
    {
        $data = [
            'id' => $resep->id,
            'resep_id' => $resep->id,
            'nama_resep' => $resep->nama_resep,
            'cara_memasak' => $resep->cara_memasak,
            'kategori_gizi' => $resep->kategori_gizi,
            'jumlah_porsi_hasil' => $resep->jumlah_porsi_hasil,
            'porsi_saji' => $resep->porsi_saji,
            'gizi_per_porsi' => $resep->giziPerPorsi(),
            'usia_min_bulan' => $resep->usia_min_bulan,
            'usia_maks_bulan' => $resep->usia_maks_bulan,
            'usia_minimal_bulan_jika_dimodifikasi' => $resep->usia_minimal_bulan_jika_dimodifikasi,
            'modifikasi_untuk_turunkan_usia' => $resep->modifikasi_untuk_turunkan_usia,
            'keyakinan_label' => $resep->keyakinan_label,
            'jenis_menu' => $resep->jenis_menu,
            'alergen' => $resep->alergen ?? [],
            'catatan_resep' => $resep->catatan_resep,
            'tekstur_sajian' => $resep->tekstur_sajian,
            'sumber_resep' => $resep->sumber_resep,
            'status_verifikasi_data' => $resep->status_verifikasi_data,
            'tingkat_kesulitan' => $resep->tingkat_kesulitan,
            'jumlah_bahan' => $resep->bahans_count ?? $resep->bahans()->count(),
        ];

        if ($denganBahan) {
            $data['bahans'] = $resep->bahans->map(fn (ResepBahan $rb) => [
                'bahan_id' => $rb->bahan_id,
                'nama_bahan' => $rb->bahan?->nama_bahan,
                'takaran_gram' => $rb->takaran_gram,
                'takaran_jumlah' => $rb->takaran_jumlah,
                'takaran_satuan' => $rb->takaran_satuan,
                'takaran_gram_ekuivalen' => $rb->takaran_gram_ekuivalen,
                'catatan_bahan' => $rb->catatan_bahan,
            ])->values();
        }

        return $data;
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
}
