<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\KondisiHarian;
use App\Models\Konsumsi;
use App\Models\Pertumbuhan;
use App\Models\User;
use App\Services\NutritionService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'bulan' => ['sometimes', 'integer', 'min:1', 'max:'.config('nutrition.kader.bulan_maks', 12)],
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
            'distribusi_kategori_pemantauan' => $this->distribusiKategori($terakhir),
            'tren_prevalensi' => $this->trenPrevalensi(
                $anakIds,
                (int) ($filter['bulan'] ?? config('nutrition.kader.bulan_default', 6)),
            ),
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
                'usia_bulan_saat_ukur' => $p->usia_bulan_saat_ukur === null
                    ? $p->umur_bulan
                    : (float) $p->usia_bulan_saat_ukur,
                'umur_bulan' => $p->umur_bulan,
                'berat_kg' => (float) $p->berat_kg,
                'tinggi_cm' => (float) $p->tinggi_cm,
                'zscore_bb_u' => $p->z_bb_u === null ? null : (float) $p->z_bb_u,
                'zscore_tb_u' => $p->z_tb_u === null ? null : (float) $p->z_tb_u,
                'zscore_bb_tb' => $p->z_bb_tb === null ? null : (float) $p->z_bb_tb,
                'kategori_pemantauan' => $p->kategoriEfektif(),
                'metode_klasifikasi' => $p->metodeKlasifikasiEfektif(),
                'versi_aturan' => $p->versiAturanEfektif(),
                'status_validitas_pengukuran' => $p->statusValiditasEfektif(),
                'pesan' => $p->pesanEfektif(),
            ]);

        AuditLogger::log($user, 'logbook_view', ['anak_id' => $anak->id]);

        $mulai = today()->subDays(6)->toDateString();

        $kondisi = KondisiHarian::query()
            ->where('anak_id', $anak->id)
            ->whereDate('tanggal_kondisi', '>=', $mulai)
            ->orderByDesc('tanggal_kondisi')
            ->get()
            ->map(fn (KondisiHarian $k) => [
                'tanggal' => $k->tanggal_kondisi->toDateString(),
                'status_kondisi' => $k->status_kondisi,
                'penanda' => $k->status_kondisi === KondisiHarian::STATUS_SAKIT
                    ? NutritionService::PENANDA_ANAK_SAKIT
                    : null,
            ])
            ->values();

        $konsumsi = Konsumsi::query()
            ->with(['bahan', 'resep', 'kondisi'])
            ->where('anak_id', $anak->id)
            ->whereDate('tanggal_konsumsi', '>=', $mulai)
            ->orderByDesc('tanggal_konsumsi')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->groupBy(fn ($k) => $k->tanggal_konsumsi->toDateString())
            ->map(function ($rows, $tgl) {
                $statusKondisi = $rows->first()->kondisi?->status_kondisi;

                return [
                    'tanggal_konsumsi' => $tgl,
                    'status_kondisi' => $statusKondisi,
                    'penanda' => $statusKondisi === KondisiHarian::STATUS_SAKIT
                        ? NutritionService::PENANDA_ANAK_SAKIT
                        : null,
                    'items' => $rows->map(fn ($r) => [
                        'konsumsi_id' => $r->id,
                        'nama_bahan' => $r->bahan->nama_bahan,
                        'porsi_gram' => (float) $r->porsi_gram,
                        'metode_input' => $r->metode_input,
                        'resep_id' => $r->resep_id,
                        'nama_resep' => $r->resep?->nama_resep,
                        'porsi_resep_dikonsumsi' => $r->porsi_resep_dikonsumsi,
                        'status_validitas' => $r->status_validitas,
                        'alasan_invalid' => $r->alasan_invalid,
                    ])->values(),
                ];
            })
            ->values();

        return ApiResponse::success([
            'anak_id' => $anak->id,
            'nama' => $anak->nama,
            'tanggal_lahir' => $anak->tanggal_lahir->toDateString(),
            'jenis_kelamin' => $anak->jenis_kelamin,
            'pemilik_nama' => $anak->user->nama,
            'wilayah' => $anak->user->wilayah_posyandu,
            'riwayat' => $riwayat,
            'kondisi_harian' => $kondisi,
            'konsumsi_terakhir' => $konsumsi,
        ], 'Logbook anak binaan');
    }

    /**
     * Prevalensi bulanan memakai pengukuran terakhir tiap anak pada bulan itu.
     *
     * @param  list<string>  $anakIds
     * @return list<array{bulan: string, label: string, normal: int, at_risk: int, high_risk: int, tidak_terklasifikasi: int, total_diukur: int, persen_perlu_perhatian: float|null, denominator_kecil: bool}>
     */
    private function trenPrevalensi(array $anakIds, int $jumlahBulan): array
    {
        $zona = config('nutrition.kader.zona_waktu', 'Asia/Jakarta');
        $bulanTerakhir = Carbon::now($zona)->startOfMonth();
        $bulanPertama = $bulanTerakhir->copy()->subMonths($jumlahBulan - 1);
        $batasAkhir = $bulanTerakhir->copy()->endOfMonth();

        $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $buckets = [];
        for ($i = 0; $i < $jumlahBulan; $i++) {
            $bulan = $bulanPertama->copy()->addMonths($i);
            $kunci = $bulan->format('Y-m');

            $buckets[$kunci] = [
                'bulan' => $kunci,
                'label' => $namaBulan[$bulan->month - 1].' '.$bulan->year,
                'normal' => 0,
                'at_risk' => 0,
                'high_risk' => 0,
                'tidak_terklasifikasi' => 0,
                'total_diukur' => 0,
                'persen_perlu_perhatian' => null,
                'denominator_kecil' => false,
            ];
        }

        if ($anakIds !== []) {
            $rows = Pertumbuhan::query()
                ->whereIn('anak_id', $anakIds)
                ->whereDate('tanggal_ukur', '>=', $bulanPertama->toDateString())
                ->whereDate('tanggal_ukur', '<=', $batasAkhir->toDateString())
                ->orderBy('tanggal_ukur')
                ->orderBy('created_at')
                ->get();

            $terakhir = [];
            foreach ($rows as $row) {
                $kunci = $row->tanggal_ukur->format('Y-m');
                $terakhir[$row->anak_id][$kunci] = $row;
            }

            foreach ($terakhir as $perBulan) {
                foreach ($perBulan as $kunci => $pengukuran) {
                    if (! isset($buckets[$kunci])) {
                        continue;
                    }

                    match ($pengukuran->kategoriEfektif()) {
                        Pertumbuhan::KATEGORI_NORMAL_LOW_RISK => $buckets[$kunci]['normal']++,
                        Pertumbuhan::KATEGORI_AT_RISK_MONITORING => $buckets[$kunci]['at_risk']++,
                        Pertumbuhan::KATEGORI_HIGH_RISK_REFERRAL => $buckets[$kunci]['high_risk']++,
                        default => $buckets[$kunci]['tidak_terklasifikasi']++,
                    };
                }
            }
        }

        $denominatorMin = (int) config('nutrition.kader.denominator_min', 5);

        foreach ($buckets as &$bucket) {
            $bucket['total_diukur'] = $bucket['normal']
                + $bucket['at_risk']
                + $bucket['high_risk']
                + $bucket['tidak_terklasifikasi'];

            if ($bucket['total_diukur'] > 0) {
                $bucket['persen_perlu_perhatian'] = round(
                    (($bucket['at_risk'] + $bucket['high_risk']) / $bucket['total_diukur']) * 100,
                    1,
                );
            }

            $bucket['denominator_kecil'] = $bucket['total_diukur'] > 0
                && $bucket['total_diukur'] < $denominatorMin;
        }
        unset($bucket);

        return array_values($buckets);
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
     * @param  list<string>  $anakIds
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
     * @param  list<Pertumbuhan>  $terakhir
     * @return array<string, int>
     */
    private function distribusiKategori(array $terakhir): array
    {
        $dist = [
            Pertumbuhan::KATEGORI_NORMAL_LOW_RISK => 0,
            Pertumbuhan::KATEGORI_AT_RISK_MONITORING => 0,
            Pertumbuhan::KATEGORI_HIGH_RISK_REFERRAL => 0,
        ];

        foreach ($terakhir as $p) {
            $kategori = $p->kategoriEfektif();

            if ($kategori !== null && isset($dist[$kategori])) {
                $dist[$kategori]++;
            }
        }

        return $dist;
    }
}
