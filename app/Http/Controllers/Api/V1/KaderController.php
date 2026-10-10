<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Konsumsi;
use App\Models\Pertumbuhan;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KaderController extends Controller
{
    /**
     * SKPL §5.4.8 (F-10): agregat anonim sewilayah.
     * Hanya kader (wilayah = profilnya) dan admin.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isKader() && ! $user->isAdmin()) {
            throw new HttpResponseException(ApiResponse::error(
                'FORBIDDEN_RESOURCE', 'Hanya kader dan admin yang dapat mengakses dashboard.', 403
            ));
        }

        $filter = $request->validate([
            'wilayah' => ['required', 'string', 'max:100'],
        ]);

        // UT-09: kader di luar wilayahnya ditolak.
        if ($user->isKader() && $filter['wilayah'] !== $user->wilayah_posyandu) {
            throw new HttpResponseException(ApiResponse::error(
                'FORBIDDEN_RESOURCE', 'Anda hanya dapat mengakses wilayah binaan Anda.', 403
            ));
        }

        $anakIds = $this->anakBinaanIds($filter['wilayah']);
        $terakhir = $this->pengukuranTerakhir($anakIds);

        return ApiResponse::success([
            'wilayah' => $filter['wilayah'],
            'total_anak_binaan' => count($anakIds),
            'anak_terukur' => count($terakhir),
            'distribusi_status_gizi' => $this->distribusiGizi($terakhir),
            'distribusi_stunting_indikatif' => $this->distribusiStunting($terakhir),
        ], 'Dashboard kader');
    }

    /**
     * SKPL §5.4.8 (F-10): logbook anak binaan, read-only.
     */
    public function logbook(Request $request, string $anakId): JsonResponse
    {
        $user = $request->user();

        if (! $user->isKader() && ! $user->isAdmin()) {
            throw new HttpResponseException(ApiResponse::error(
                'FORBIDDEN_RESOURCE', 'Hanya kader dan admin yang dapat mengakses logbook.', 403
            ));
        }

        $anak = Anak::with('user')->find($anakId);

        if ($anak === null) {
            throw new HttpResponseException(ApiResponse::error(
                'RESOURCE_NOT_FOUND', 'Data anak tidak ditemukan.', 404
            ));
        }

        Gate::authorize('view', $anak);

        $riwayat = Pertumbuhan::query()
            ->where('anak_id', $anak->id)
            ->orderBy('tanggal_ukur')
            ->get()
            ->map(fn (Pertumbuhan $p) => [
                'tanggal_ukur' => $p->tanggal_ukur->toDateString(),
                'umur_bulan' => $p->umur_bulan,
                'berat_kg' => (float) $p->berat_kg,
                'tinggi_cm' => (float) $p->tinggi_cm,
                'zscore_bb_u' => $p->z_bb_u === null ? null : (float) $p->z_bb_u,
                'zscore_tb_u' => $p->z_tb_u === null ? null : (float) $p->z_tb_u,
                'zscore_bb_tb' => $p->z_bb_tb === null ? null : (float) $p->z_bb_tb,
                'status_gizi_antropometri' => $p->status_gizi_antropometri,
            ]);

        AuditLogger::log($user, 'logbook_view', ['anak_id' => $anak->id]);

        $konsumsi = Konsumsi::query()
            ->with('bahan')
            ->where('anak_id', $anak->id)
            ->where('tanggal_konsumsi', '>=', today()->subDays(6)->toDateString())
            ->orderByDesc('tanggal_konsumsi')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->groupBy(fn ($k) => $k->tanggal_konsumsi->toDateString())
            ->map(fn ($rows, $tgl) => [
                'tanggal_konsumsi' => $tgl,
                'items' => $rows->map(fn ($r) => [
                    'nama_bahan' => $r->bahan->nama_bahan,
                    'porsi_gram' => (float) $r->porsi_gram,
                ])->values(),
            ])
            ->values();

        return ApiResponse::success([
            'anak_id' => $anak->id,
            'nama' => $anak->nama,
            'tanggal_lahir' => $anak->tanggal_lahir->toDateString(),
            'jenis_kelamin' => $anak->jenis_kelamin,
            'pemilik_nama' => $anak->user->nama,
            'wilayah' => $anak->user->wilayah_posyandu,
            'riwayat' => $riwayat,
            'konsumsi_terakhir' => $konsumsi,
        ], 'Logbook anak binaan');
    }

    /**
     * @return list<string>
     */
    private function anakBinaanIds(string $wilayah): array
    {
        return Anak::query()
            ->whereIn('user_id', User::query()->where('wilayah_posyandu', $wilayah)->pluck('id'))
            ->pluck('id')
            ->all();
    }

    /**
     * Pengukuran terakhir tiap anak (satu baris per anak).
     *
     * @param list<string> $anakIds
     * @return list<Pertumbuhan>
     */
    private function pengukuranTerakhir(array $anakIds): array
    {
        if ($anakIds === []) {
            return [];
        }

        $rows = Pertumbuhan::query()
            ->whereIn('anak_id', $anakIds)
            ->orderByDesc('tanggal_ukur')
            ->orderByDesc('created_at')
            ->get();

        $terakhir = [];
        foreach ($rows as $row) {
            $terakhir[$row->anak_id] ??= $row;
        }

        return array_values($terakhir);
    }

    /**
     * @param list<Pertumbuhan> $terakhir
     * @return array<string, int>
     */
    private function distribusiGizi(array $terakhir): array
    {
        $dist = ['baik' => 0, 'kurang' => 0, 'sangat_kurang' => 0, 'lebih' => 0];

        foreach ($terakhir as $p) {
            match ($p->status_bb_tb) {
                'gizi_baik' => $dist['baik']++,
                'gizi_kurang' => $dist['kurang']++,
                'gizi_buruk' => $dist['sangat_kurang']++,
                'berisiko_gizi_lebih', 'gizi_lebih', 'obesitas' => $dist['lebih']++,
                default => null,
            };
        }

        return $dist;
    }

    /**
     * @param list<Pertumbuhan> $terakhir
     * @return array<string, int>
     */
    private function distribusiStunting(array $terakhir): array
    {
        $dist = ['normal' => 0, 'pendek' => 0, 'sangat_pendek' => 0, 'tinggi' => 0];

        foreach ($terakhir as $p) {
            match ($p->status_tb_u) {
                'normal' => $dist['normal']++,
                'pendek' => $dist['pendek']++,
                'sangat_pendek' => $dist['sangat_pendek']++,
                'tinggi' => $dist['tinggi']++,
                default => null,
            };
        }

        return $dist;
    }
}
