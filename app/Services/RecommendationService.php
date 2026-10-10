<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\RekomendasiLog;
use App\Models\Resep;
use Illuminate\Support\Facades\DB;

/**
 * Recommendation Engine (SKPL-F-08): penyaringan resep constraint-based
 * berdasarkan bahan tersedia dan/atau anggaran, relevan dengan defisiensi.
 */
class RecommendationService
{
    public function __construct(private readonly NutritionService $gizi) {}

    /**
     * @param list<string> $bahanTersedia
     * @return array{jumlah_rekomendasi: int, menu: list<array{resep_id: string, nama_resep: string, estimasi_biaya: float|null, kategori_gizi: string, kecocokan_bahan: string}>, defisiensi_terdeteksi: list<string>, saran: string|null, tanpa_info_biaya: int}
     */
    public function rekomendasikan(
        Anak $anak,
        array $bahanTersedia = [],
        ?float $anggaranMax = null,
        ?string $tanggal = null,
        ?string $dimintaOleh = null,
    ): array {
        $tanggal ??= today()->toDateString();
        $analisis = $this->gizi->analisis($anak, $tanggal);

        $defisiensi = collect($analisis['ringkasan'])
            ->where('status', '!==', 'cukup')
            ->pluck('zat_gizi')
            ->values()
            ->all();

        $reseps = Resep::query()->with('bahans.bahan')->orderBy('nama_resep')->get();

        $kandidat = [];
        $tanpaBiaya = 0;

        foreach ($reseps as $resep) {
            $bahanResep = $resep->bahans->pluck('bahan_id')->all();
            $cocok = count(array_intersect($bahanResep, $bahanTersedia));
            $persen = $bahanResep === [] ? 0 : (int) round($cocok / count($bahanResep) * 100);

            // Mode bahan: tanpa daftar bahan, kecocokan 0 untuk semua.
            if ($bahanTersedia !== [] && $persen === 0) {
                continue;
            }

            $biaya = $resep->estimasi_biaya === null ? null : (float) $resep->estimasi_biaya;

            // Mode anggaran: lewati resep tanpa info biaya (jujur, bukan tebakan).
            if ($anggaranMax !== null) {
                if ($biaya === null) {
                    $tanpaBiaya++;

                    continue;
                }

                if ($biaya > $anggaranMax) {
                    continue;
                }
            }

            $kandidat[] = [
                'resep' => $resep,
                'kecocokan' => $persen,
                'relevan' => $this->relevan($resep->kategori_gizi, $defisiensi),
            ];
        }

        // Urut: kecocokan desc, relevansi gizi, biaya asc.
        usort($kandidat, fn ($a, $b) =>
            [$b['kecocokan'], $b['relevan'] ? 0 : 1, $a['resep']->estimasi_biaya ?? PHP_INT_MAX]
            <=>
            [$a['kecocokan'], $a['relevan'] ? 0 : 1, $b['resep']->estimasi_biaya ?? PHP_INT_MAX]
        );

        $menu = array_map(fn ($k) => [
            'resep_id' => $k['resep']->id,
            'nama_resep' => $k['resep']->nama_resep,
            'estimasi_biaya' => $k['resep']->estimasi_biaya === null ? null : (float) $k['resep']->estimasi_biaya,
            'kategori_gizi' => $k['resep']->kategori_gizi,
            'kecocokan_bahan' => $k['kecocokan'].'%',
        ], $kandidat);

        $saran = null;
        if ($menu === [] && $anggaranMax !== null) {
            $saran = 'Tidak ada resep dalam anggaran tersebut. Coba naikkan anggaran_max atau tambah bahan tersedia.';
        }

        DB::transaction(function () use ($anak, $tanggal, $bahanTersedia, $anggaranMax, $defisiensi, $menu, $dimintaOleh) {
            RekomendasiLog::create([
                'anak_id' => $anak->id,
                'tanggal_minta' => $tanggal,
                'masukan' => ['bahan_tersedia' => $bahanTersedia, 'anggaran_max' => $anggaranMax],
                'defisiensi' => $defisiensi,
                'jumlah_hasil' => count($menu),
                'diminta_oleh' => $dimintaOleh,
            ]);
        });

        return [
            'jumlah_rekomendasi' => count($menu),
            'menu' => $menu,
            'defisiensi_terdeteksi' => $defisiensi,
            'saran' => $saran,
            'tanpa_info_biaya' => $tanpaBiaya,
        ];
    }

    /**
     * Relevan bila kategori menyebut salah satu zat defisien
     * (atau tak ada defisiensi terdeteksi).
     *
     * @param list<string> $defisiensi
     */
    private function relevan(string $kategori, array $defisiensi): bool
    {
        if ($defisiensi === []) {
            return true;
        }

        $kat = mb_strtolower($kategori);

        foreach ($defisiensi as $zat) {
            $kunci = match ($zat) {
                'zat_besi' => ['besi', 'zat besi', 'iron'],
                'karbohidrat' => ['karbohidrat', 'karbo'],
                default => [str_replace('_', ' ', $zat)],
            };

            foreach ($kunci as $k) {
                if (str_contains($kat, $k)) {
                    return true;
                }
            }
        }

        return false;
    }
}
