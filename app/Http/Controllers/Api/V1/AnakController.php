<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnakRequest;
use App\Http\Requests\UpdateAnakRequest;
use App\Models\Anak;
use App\Models\PantanganAnak;
use App\Models\Pertumbuhan;
use App\Models\User;
use App\Services\ZScoreService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AnakController extends Controller
{
    /**
     * SKPL §5.4.4: daftar anak milik sendiri; kader mendapat anak sewilayah.
     * SKPL §5.4.1: dukung ?page & ?limit (default 20, maks 100).
     */
    public function index(Request $request): JsonResponse
    {
        $paging = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'ringkas_ukur' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $query = Anak::query()->with('user')->orderBy('nama');

        if ($user->isAdmin()) {
            // Semua anak.
        } elseif ($user->isKader() && $user->wilayah_posyandu !== null) {
            $userIds = User::query()
                ->where('wilayah_posyandu', $user->wilayah_posyandu)
                ->pluck('id');
            $query->whereIn('user_id', $userIds);
        } else {
            $query->where('user_id', $user->id);
        }

        $page = $query->paginate($paging['limit'] ?? 20);

        // Ukur terakhir per anak: satu query + group di PHP (aman untuk UUID di PG).
        $terakhir = collect();
        if ($request->boolean('ringkas_ukur')) {
            $terakhir = Pertumbuhan::query()
                ->whereIn('anak_id', $page->getCollection()->pluck('id'))
                ->orderByDesc('tanggal_ukur')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('anak_id')
                ->map(fn ($rows) => $rows->first());
        }

        $page->getCollection()->transform(fn (Anak $anak) => $this->ringkas(
            $anak,
            $terakhir->get($anak->id),
            $request->boolean('ringkas_ukur')
        ));

        return ApiResponse::success($page, 'Daftar anak');
    }

    /**
     * SKPL §5.4.4: tambah profil anak baru (pemilik = pengguna login).
     */
    public function store(StoreAnakRequest $request): JsonResponse
    {
        $anak = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $pantangan = $data['pantangan'] ?? [];
            unset($data['pantangan']);

            $created = Anak::create([
                ...$data,
                'user_id' => $request->user()->id,
            ]);

            $this->simpanPantangan($created, $pantangan);

            AuditLogger::log($request->user(), 'anak_create', ['anak_id' => $created->id]);

            return $created;
        });

        return ApiResponse::success($this->ringkas($anak->load('user')), 'Data anak tersimpan', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $anak = $this->findOrFail($request, $id, 'view');

        return ApiResponse::success($this->detail($anak), 'Detail anak');
    }

    /**
     * SKPL §5.4.4: PUT oleh pemilik data.
     */
    public function update(UpdateAnakRequest $request, string $id): JsonResponse
    {
        $anak = $this->findOrFail($request, $id, 'update');

        DB::transaction(function () use ($request, $anak) {
            $data = $request->validated();
            $punyaPantangan = array_key_exists('pantangan', $data);
            $pantangan = $data['pantangan'] ?? [];
            unset($data['pantangan']);

            $anak->update($data);

            if ($punyaPantangan) {
                $this->simpanPantangan($anak, $pantangan);
            }

            AuditLogger::log($request->user(), 'anak_update', ['anak_id' => $anak->id]);
        });

        return ApiResponse::success($this->ringkas($anak->refresh()->load('user')), 'Data anak diperbarui');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $anak = $this->findOrFail($request, $id, 'delete');

        DB::transaction(function () use ($request, $anak, $id) {
            $anak->delete();

            AuditLogger::log($request->user(), 'anak_delete', ['anak_id' => $id]);
        });

        return ApiResponse::success([], 'Data anak dihapus');
    }

    private function findOrFail(Request $request, string $id, string $ability): Anak
    {
        $anak = Anak::with(['user', 'pantangan.bahan'])->find($id);

        if ($anak === null) {
            throw new HttpResponseException(ApiResponse::error('RESOURCE_NOT_FOUND', 'Data anak tidak ditemukan.', 404));
        }

        Gate::authorize($ability, $anak);

        return $anak;
    }

    /**
     * @param  list<array{bahan_id?: ?string, alergen?: ?string, jenis?: ?string}>  $pantangan
     */
    private function simpanPantangan(Anak $anak, array $pantangan): void
    {
        $anak->pantangan()->delete();
        $unik = [];

        foreach ($pantangan as $item) {
            $bahanId = $item['bahan_id'] ?? null;
            $alergen = trim((string) ($item['alergen'] ?? ''));
            $alergen = $alergen === '' ? null : $alergen;

            if ($bahanId === null && $alergen === null) {
                continue;
            }

            $jenis = $item['jenis'] ?? PantanganAnak::JENIS_ALERGI;
            $kunci = ($bahanId ?? '').'|'.($alergen ?? '').'|'.$jenis;
            if (isset($unik[$kunci])) {
                continue;
            }

            $unik[$kunci] = true;
            $anak->pantangan()->create([
                'bahan_id' => $bahanId,
                'alergen' => $alergen,
                'jenis' => $jenis,
            ]);
        }
    }

    /**
     * @return array{anak_id: string, nama: string, tanggal_lahir: string, usia_bulan: int, jenis_kelamin: string}
     */
    private function ringkas(Anak $anak, ?Pertumbuhan $ukurTerakhir = null, bool $sertakanUkur = false): array
    {
        $data = [
            'anak_id' => $anak->id,
            'nama' => $anak->nama,
            'tanggal_lahir' => $anak->tanggal_lahir->toDateString(),
            'usia_bulan' => ZScoreService::umurBulanPenuh(
                $anak->tanggal_lahir->toDateString(), today()->toDateString()
            ),
            'jenis_kelamin' => $anak->jenis_kelamin,
            'pemilik_nama' => $anak->user->nama,
            'wilayah' => $anak->user->wilayah_posyandu,
        ];

        if ($sertakanUkur) {
            $ukur = $ukurTerakhir;
            $data['ukur_terakhir'] = $ukur === null ? null : [
                'tanggal_ukur' => $ukur->tanggal_ukur->toDateString(),
                'umur_bulan' => $ukur->umur_bulan,
                'berat_kg' => (float) $ukur->berat_kg,
                'tinggi_cm' => (float) $ukur->tinggi_cm,
                'zscore_bb_u' => $ukur->z_bb_u === null ? null : (float) $ukur->z_bb_u,
                'zscore_tb_u' => $ukur->z_tb_u === null ? null : (float) $ukur->z_tb_u,
                'zscore_bb_tb' => $ukur->z_bb_tb === null ? null : (float) $ukur->z_bb_tb,
                'kategori_pemantauan' => $ukur->kategoriEfektif(),
                'metode_klasifikasi' => $ukur->metodeKlasifikasiEfektif(),
                'versi_aturan' => $ukur->versiAturanEfektif(),
                'status_validitas_pengukuran' => $ukur->statusValiditasEfektif(),
                'pesan' => $ukur->pesanEfektif(),
                'status_gizi_antropometri' => $ukur->status_gizi_antropometri,
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Anak $anak): array
    {
        $anak->loadMissing('pantangan.bahan');

        return [
            ...$this->ringkas($anak),
            'alamat' => $anak->alamat,
            'pemilik_wilayah' => $anak->user->wilayah_posyandu,
            'pantangan' => $anak->pantangan->map(fn (PantanganAnak $item) => [
                'id' => $item->id,
                'bahan_id' => $item->bahan_id,
                'nama_bahan' => $item->bahan?->nama_bahan,
                'alergen' => $item->alergen,
                'jenis' => $item->jenis,
            ])->values(),
        ];
    }
}
