<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\KondisiHarian;
use App\Models\Konsumsi;
use App\Models\Resep;
use App\Models\ResepBahan;
use App\Services\NutritionService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
            'metode_input' => ['required', 'in:MANUAL,RESEP'],
            'resep_id' => ['nullable', 'uuid', 'exists:reseps,id', 'required_if:metode_input,RESEP', 'prohibited_if:metode_input,MANUAL'],
            'jumlah_porsi_dikonsumsi' => ['nullable', 'numeric', 'min:0.1', 'max:99.99', 'prohibited_if:metode_input,MANUAL'],
            'porsi_resep_dikonsumsi' => ['nullable', 'numeric', 'min:0.1', 'max:99.99', 'prohibited_if:metode_input,MANUAL'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.bahan_id' => ['required', 'uuid', 'exists:bahan_makanans,id'],
            'items.*.jumlah' => ['nullable', 'numeric', 'gt:0'],
            'items.*.porsi_gram' => ['nullable', 'numeric', 'gt:0'],
            'items.*.satuan' => ['sometimes', 'in:g,gram,kg'],
        ]);

        if (CarbonImmutable::parse($data['tanggal_konsumsi'])->lt($anak->tanggal_lahir)) {
            throw ValidationException::withMessages([
                'tanggal_konsumsi' => 'Tanggal konsumsi tidak boleh sebelum tanggal lahir anak.',
            ]);
        }

        $porsiResep = null;
        $resep = null;
        $resepBahans = collect();

        if ($data['metode_input'] === Konsumsi::METODE_RESEP) {
            $porsiResep = $data['jumlah_porsi_dikonsumsi'] ?? $data['porsi_resep_dikonsumsi'] ?? null;

            if ($porsiResep === null) {
                throw ValidationException::withMessages([
                    'jumlah_porsi_dikonsumsi' => 'Jumlah porsi resep yang dikonsumsi wajib diisi.',
                ]);
            }

            $resep = Resep::with('bahans')->find($data['resep_id']);

            if ($resep === null) {
                throw ValidationException::withMessages([
                    'resep_id' => 'Resep tidak ditemukan.',
                ]);
            }

            $resepBahans = $resep->bahans->keyBy('bahan_id');
            $bahanDipilih = collect($data['items'])->pluck('bahan_id')->unique();
            $tidakSesuai = $bahanDipilih->diff($resepBahans->keys());

            if ($tidakSesuai->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Ada bahan yang tidak termasuk dalam resep yang dipilih.',
                ]);
            }
        }

        $itemsSiap = [];
        foreach ($data['items'] as $index => $item) {
            $gram = $this->gramDariItem($item);

            if ($gram === null) {
                if ($data['metode_input'] === Konsumsi::METODE_MANUAL) {
                    throw ValidationException::withMessages([
                        "items.{$index}.jumlah" => 'Jumlah bahan wajib diisi.',
                    ]);
                }

                $gram = $this->gramDariResep($item, $resep, $resepBahans, (float) $porsiResep, $index);
            }

            if ($gram < 0.1 || $gram > 999999.99) {
                throw ValidationException::withMessages([
                    "items.{$index}.jumlah" => 'Jumlah bahan setelah konversi harus antara 0,1 dan 999.999,99 gram.',
                ]);
            }

            $itemsSiap[] = [
                'bahan_id' => $item['bahan_id'],
                'gram' => $gram,
            ];
        }

        $hasil = DB::transaction(function () use ($request, $anak, $data, $porsiResep, $itemsSiap) {
            $kondisi = KondisiHarian::query()
                ->where('anak_id', $anak->id)
                ->whereDate('tanggal_kondisi', $data['tanggal_konsumsi'])
                ->lockForUpdate()
                ->first();

            if ($kondisi === null) {
                throw new HttpResponseException(ApiResponse::error(
                    'KONDISI_BELUM_DIISI',
                    'Isi kondisi anak terlebih dahulu untuk tanggal konsumsi ini.',
                    400,
                ));
            }

            $sakit = $kondisi->status_kondisi === KondisiHarian::STATUS_SAKIT;
            $validitas = $sakit ? Konsumsi::INVALID : Konsumsi::VALID;
            $alasan = $sakit ? Konsumsi::ALASAN_ANAK_SAKIT : null;
            $dibuat = [];

            foreach ($itemsSiap as $item) {
                $dibuat[] = Konsumsi::create([
                    'anak_id' => $anak->id,
                    'kondisi_id' => $kondisi->id,
                    'bahan_id' => $item['bahan_id'],
                    'resep_id' => $data['metode_input'] === Konsumsi::METODE_RESEP ? $data['resep_id'] : null,
                    'porsi_gram' => $item['gram'],
                    'porsi_resep_dikonsumsi' => $data['metode_input'] === Konsumsi::METODE_RESEP
                        ? round((float) $porsiResep, 2)
                        : null,
                    'tanggal_konsumsi' => $data['tanggal_konsumsi'],
                    'metode_input' => $data['metode_input'],
                    'status_validitas' => $validitas,
                    'alasan_invalid' => $alasan,
                    'dicatat_oleh' => $request->user()->id,
                ])->id;
            }

            AuditLogger::log($request->user(), 'konsumsi_create', [
                'anak_id' => $anak->id,
                'tanggal_konsumsi' => $data['tanggal_konsumsi'],
                'jumlah_item' => count($dibuat),
                'metode_input' => $data['metode_input'],
                'status_validitas' => $validitas,
            ], [
                'entitas_target' => 'konsumsi_harian',
                'nilai_baru' => [
                    'tanggal_konsumsi' => $data['tanggal_konsumsi'],
                    'metode_input' => $data['metode_input'],
                    'resep_id' => $data['resep_id'] ?? null,
                    'status_validitas' => $validitas,
                    'jumlah_item' => count($dibuat),
                ],
            ]);

            return [
                'ids' => $dibuat,
                'status_kondisi' => $kondisi->status_kondisi,
                'status_validitas' => $validitas,
                'alasan_invalid' => $alasan,
            ];
        });

        return ApiResponse::success([
            'tanggal_konsumsi' => $data['tanggal_konsumsi'],
            'jumlah_item' => count($hasil['ids']),
            'konsumsi_ids' => $hasil['ids'],
            'metode_input' => $data['metode_input'],
            'resep_id' => $data['resep_id'] ?? null,
            'porsi_resep_dikonsumsi' => $porsiResep !== null ? round((float) $porsiResep, 2) : null,
            'status_kondisi' => $hasil['status_kondisi'],
            'status_validitas' => $hasil['status_validitas'],
            'alasan_invalid' => $hasil['alasan_invalid'],
        ], 'Konsumsi tersimpan', 201);
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
            ->with(['bahan', 'resep', 'kondisi'])
            ->where('anak_id', $anak->id)
            ->when(isset($filter['tanggal']), fn ($q) => $q->whereDate('tanggal_konsumsi', $filter['tanggal']))
            ->when(isset($filter['dari_tanggal']), fn ($q) => $q->whereDate('tanggal_konsumsi', '>=', $filter['dari_tanggal']))
            ->when(isset($filter['sampai_tanggal']), fn ($q) => $q->whereDate('tanggal_konsumsi', '<=', $filter['sampai_tanggal']))
            ->orderBy('tanggal_konsumsi')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Konsumsi $k) => [
                'konsumsi_id' => $k->id,
                'bahan_id' => $k->bahan_id,
                'nama_bahan' => $k->bahan->nama_bahan,
                'porsi_gram' => (float) $k->porsi_gram,
                'tanggal_konsumsi' => $k->tanggal_konsumsi->toDateString(),
                'metode_input' => $k->metode_input,
                'resep_id' => $k->resep_id,
                'nama_resep' => $k->resep?->nama_resep,
                'porsi_resep_dikonsumsi' => $k->porsi_resep_dikonsumsi,
                'status_validitas' => $k->status_validitas,
                'alasan_invalid' => $k->alasan_invalid,
                'status_kondisi' => $k->kondisi?->status_kondisi,
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

    /**
     * @param  array{bahan_id: string, jumlah?: float|int|string|null, porsi_gram?: float|int|string|null, satuan?: string}  $item
     */
    private function gramDariItem(array $item): ?float
    {
        if (($item['porsi_gram'] ?? null) !== null) {
            $gram = $item['porsi_gram'];
        } elseif (($item['jumlah'] ?? null) !== null) {
            $gram = $item['jumlah'];
        } else {
            return null;
        }

        if (($item['satuan'] ?? 'g') === 'kg') {
            $gram = (float) $gram * 1000.0;
        }

        return round((float) $gram, 2);
    }

    /**
     * @param  array{bahan_id: string, jumlah?: float|int|string|null, porsi_gram?: float|int|string|null, satuan?: string}  $item
     * @param  Collection<string, ResepBahan>  $resepBahans
     */
    private function gramDariResep(
        array $item,
        ?Resep $resep,
        Collection $resepBahans,
        float $porsi,
        int $index,
    ): float {
        $resepBahan = $resepBahans->get($item['bahan_id']);
        $ekuivalen = $resepBahan?->takaran_gram_ekuivalen ?? $resepBahan?->takaran_gram;
        $jumlahPorsiHasil = (float) ($resep?->jumlah_porsi_hasil ?? $resep?->porsi_saji ?? 0);

        if ($ekuivalen === null || (float) $ekuivalen <= 0 || $jumlahPorsiHasil <= 0) {
            throw ValidationException::withMessages([
                "items.{$index}.jumlah" => 'Takaran bahan resep belum memiliki ekuivalen gram yang terverifikasi.',
            ]);
        }

        return round(((float) $ekuivalen / $jumlahPorsiHasil) * $porsi, 2);
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
