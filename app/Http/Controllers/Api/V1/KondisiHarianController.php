<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\KondisiHarian;
use App\Models\Konsumsi;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class KondisiHarianController extends Controller
{
    public function store(Request $request, string $anakId): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'update');

        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'status_kondisi' => ['required', 'in:SAKIT,TIDAK_SAKIT'],
        ]);

        if (CarbonImmutable::parse($data['tanggal'])->lt($anak->tanggal_lahir)) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tanggal kondisi tidak boleh sebelum tanggal lahir anak.',
            ]);
        }

        $hasil = DB::transaction(function () use ($request, $anak, $data) {
            $kondisi = KondisiHarian::query()
                ->where('anak_id', $anak->id)
                ->whereDate('tanggal_kondisi', $data['tanggal'])
                ->lockForUpdate()
                ->first();

            $baru = $kondisi === null;
            $statusLama = $kondisi?->status_kondisi;

            if ($baru) {
                $kondisi = KondisiHarian::create([
                    'anak_id' => $anak->id,
                    'tanggal_kondisi' => $data['tanggal'],
                    'status_kondisi' => $data['status_kondisi'],
                    'dicatat_oleh' => $request->user()->id,
                ]);

                AuditLogger::log($request->user(), 'kondisi_harian_create', [
                    'anak_id' => $anak->id,
                    'tanggal' => $data['tanggal'],
                    'status_kondisi' => $data['status_kondisi'],
                ], [
                    'entitas_target' => 'kondisi_harian',
                    'entitas_id' => $kondisi->id,
                    'nilai_baru' => ['status_kondisi' => $data['status_kondisi']],
                ]);
            } elseif ($statusLama !== $data['status_kondisi']) {
                $kondisi->update([
                    'status_kondisi' => $data['status_kondisi'],
                    'dicatat_oleh' => $request->user()->id,
                ]);

                AuditLogger::log($request->user(), 'kondisi_harian_update', [
                    'anak_id' => $anak->id,
                    'tanggal' => $data['tanggal'],
                ], [
                    'entitas_target' => 'kondisi_harian',
                    'entitas_id' => $kondisi->id,
                    'nilai_lama' => ['status_kondisi' => $statusLama],
                    'nilai_baru' => ['status_kondisi' => $data['status_kondisi']],
                ]);
            }

            $sakit = $data['status_kondisi'] === KondisiHarian::STATUS_SAKIT;
            $validitas = $sakit ? Konsumsi::INVALID : Konsumsi::VALID;
            $alasan = $sakit ? Konsumsi::ALASAN_ANAK_SAKIT : null;
            $terdampak = 0;

            $konsumsi = Konsumsi::query()
                ->where('anak_id', $anak->id)
                ->whereDate('tanggal_konsumsi', $data['tanggal'])
                ->get();

            foreach ($konsumsi as $row) {
                $validitasBerubah = $row->status_validitas !== $validitas
                    || $row->alasan_invalid !== $alasan;
                $perluTautkan = $row->kondisi_id !== $kondisi->id;

                if (! $validitasBerubah && ! $perluTautkan) {
                    continue;
                }

                $nilaiLama = [
                    'status_validitas' => $row->status_validitas,
                    'alasan_invalid' => $row->alasan_invalid,
                ];

                $row->update([
                    'kondisi_id' => $kondisi->id,
                    'status_validitas' => $validitas,
                    'alasan_invalid' => $alasan,
                ]);

                if (! $validitasBerubah) {
                    continue;
                }

                AuditLogger::log($request->user(), 'konsumsi_validitas_update', [
                    'anak_id' => $anak->id,
                    'tanggal' => $data['tanggal'],
                    'konsumsi_id' => $row->id,
                ], [
                    'entitas_target' => 'konsumsi_harian',
                    'entitas_id' => $row->id,
                    'nilai_lama' => $nilaiLama,
                    'nilai_baru' => [
                        'status_validitas' => $validitas,
                        'alasan_invalid' => $alasan,
                    ],
                ]);

                $terdampak++;
            }

            return [
                'kondisi' => $kondisi,
                'baru' => $baru,
                'terdampak' => $terdampak,
            ];
        });

        return ApiResponse::success([
            'kondisi_id' => $hasil['kondisi']->id,
            'tanggal' => $hasil['kondisi']->tanggal_kondisi->toDateString(),
            'status_kondisi' => $hasil['kondisi']->status_kondisi,
            'konsumsi_terdampak' => $hasil['terdampak'],
        ], 'Kondisi anak tersimpan', $hasil['baru'] ? 201 : 200);
    }

    public function show(Request $request, string $anakId): JsonResponse
    {
        $anak = $this->anakOrFail($request, $anakId, 'view');

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
        ]);

        $kondisi = KondisiHarian::query()
            ->where('anak_id', $anak->id)
            ->whereDate('tanggal_kondisi', $data['tanggal'])
            ->first();

        if ($kondisi === null) {
            throw new HttpResponseException(ApiResponse::error(
                'RESOURCE_NOT_FOUND',
                'Kondisi anak belum diisi untuk tanggal ini.',
                404,
            ));
        }

        return ApiResponse::success([
            'kondisi_id' => $kondisi->id,
            'tanggal' => $kondisi->tanggal_kondisi->toDateString(),
            'status_kondisi' => $kondisi->status_kondisi,
        ], 'Kondisi anak');
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
