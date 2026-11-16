<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\Pertumbuhan;
use App\Models\WhoStandard;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Perhitungan Z-score 3 indeks (bb_u, tb_u, bb_tb — tanpa IMT/U).
 *
 * Aturan terkunci proyek:
 * - umur = bulan-penuh (2 bln 29 hr -> 2).
 * - koreksi posisi ±0,7 cm; posisi_ukur opsional (null = tanpa koreksi).
 * - ambang kategori persis Permenkes No. 2/2020 (lihat MD arsip).
 */
class ZScoreService
{
    public const PESAN_NORMAL = 'Hasil ini bukan diagnosis. Lanjutkan pemantauan rutin dan konsultasikan dengan kader atau tenaga kesehatan bila diperlukan.';

    public const PESAN_AT_RISK = 'Hasil ini bukan diagnosis. Lakukan pemantauan lebih dekat dan konsultasikan dengan kader atau tenaga kesehatan.';

    public const PESAN_HIGH_RISK = 'Hasil ini bukan diagnosis. Segera konsultasikan dengan kader atau tenaga kesehatan sesuai alur rujukan.';

    public const PESAN_UMUM = 'Hasil ini bukan diagnosis. Konsultasikan dengan kader atau tenaga kesehatan bila diperlukan.';

    /**
     * @return array{umur_bulan: int, tinggi_koreksi_cm: float, z_bb_u: float, z_tb_u: float, z_bb_tb: float, status_bb_u: string, status_tb_u: string, status_bb_tb: string, kategori_pemantauan: string, metode_klasifikasi: string, versi_aturan: string, pesan: string}
     */
    public function hitung(Anak $anak, float $beratKg, float $tinggiCm, string $tanggalUkur, ?string $posisiUkur): array
    {
        $umur = self::umurBulanPenuh($anak->tanggal_lahir->toDateString(), $tanggalUkur);

        $umurMin = (int) config('nutrition.zscore.umur_min_bulan', 0);
        $umurMax = (int) config('nutrition.zscore.umur_max_bulan', 60);

        if ($umur < $umurMin || $umur > $umurMax) {
            throw ValidationException::withMessages([
                'tanggal_ukur' => ["Umur anak saat pengukuran di luar rentang {$umurMin}-{$umurMax} bulan."],
            ]);
        }

        $jk = $anak->jenis_kelamin;
        // Batas 24 bulan ikut rujukan tinggi (pengukuran berdiri ≥ 2 tahun).
        $varian = $umur < 24 ? 'pb' : 'tb';
        $tinggiKoreksi = self::koreksiTinggi($umur, $tinggiCm, $posisiUkur);

        $zBbU = $this->zDariUmur(WhoStandard::BB_U, $jk, null, $umur, $beratKg);
        $zTbU = $this->zDariUmur(WhoStandard::TB_U, $jk, $varian, $umur, $tinggiKoreksi);
        $zBbTb = $this->zDariPanjang($jk, $varian, $tinggiKoreksi, $beratKg);

        $statusBbU = self::kategoriBbU($zBbU);
        $statusTbU = self::kategoriTbU($zTbU);
        $statusBbTb = self::kategoriBbTb($zBbTb);
        $kategori = self::kategoriPemantauan($zBbU, $zTbU, $zBbTb);

        return [
            'umur_bulan' => $umur,
            'tinggi_koreksi_cm' => $tinggiKoreksi,
            'z_bb_u' => $zBbU,
            'z_tb_u' => $zTbU,
            'z_bb_tb' => $zBbTb,
            'status_bb_u' => $statusBbU,
            'status_tb_u' => $statusTbU,
            'status_bb_tb' => $statusBbTb,
            'kategori_pemantauan' => $kategori,
            'metode_klasifikasi' => Pertumbuhan::METODE_ACUAN_ATURAN,
            'versi_aturan' => self::versiAturan(),
            'pesan' => self::pesanKategori($kategori),
        ];
    }

    public static function versiAturan(): string
    {
        return (string) config('nutrition.zscore.versi_aturan', 'permenkes-2-2020-baseline-v1');
    }

    /**
     * Baseline kategori pemantauan (KONF-02 belum final).
     *
     * Kategori ini adalah rancangan sistem, bukan diagnosis WHO/Kemenkes.
     */
    public static function kategoriPemantauan(float $zBbU, float $zTbU, float $zBbTb): string
    {
        $ambang = config('nutrition.zscore.ambang.pemantauan');

        if ($zBbU < $ambang['high_risk_max']
            || $zTbU < $ambang['high_risk_max']
            || $zBbTb < $ambang['high_risk_max']) {
            return Pertumbuhan::KATEGORI_HIGH_RISK_REFERRAL;
        }

        if ($zBbU < $ambang['at_risk_max']
            || $zTbU < $ambang['at_risk_max']
            || $zBbTb < $ambang['at_risk_max']
            || $zBbTb > $ambang['bb_tb_at_risk_max']
            || $zBbU > $ambang['bb_u_at_risk_max']) {
            return Pertumbuhan::KATEGORI_AT_RISK_MONITORING;
        }

        return Pertumbuhan::KATEGORI_NORMAL_LOW_RISK;
    }

    public static function pesanKategori(string $kategori): string
    {
        return match ($kategori) {
            Pertumbuhan::KATEGORI_HIGH_RISK_REFERRAL => self::PESAN_HIGH_RISK,
            Pertumbuhan::KATEGORI_AT_RISK_MONITORING => self::PESAN_AT_RISK,
            default => self::PESAN_NORMAL,
        };
    }

    /**
     * Bulan-penuh: selisih tanggal dibulatkan ke bawah.
     */
    public static function umurBulanPenuh(string $tanggalLahir, string $tanggalUkur): int
    {
        $lahir = Carbon::parse($tanggalLahir)->startOfDay();
        $ukur = Carbon::parse($tanggalUkur)->startOfDay();

        if ($ukur->lt($lahir)) {
            throw ValidationException::withMessages([
                'tanggal_ukur' => ['Tanggal ukur tidak boleh sebelum tanggal lahir.'],
            ]);
        }

        return (int) $lahir->diffInMonths($ukur);
    }

    /**
     * Koreksi posisi ukur (Permenkes §2.01): 0-24 bln berdiri -> +0,7;
     * > 24 bln terlentang -> -0,7; posisi null -> tanpa koreksi.
     */
    public static function koreksiTinggi(int $umurBulan, float $tinggiCm, ?string $posisiUkur): float
    {
        $koreksi = 0.0;

        if ($posisiUkur === 'berdiri' && $umurBulan <= 24) {
            $koreksi = 0.7;
        } elseif ($posisiUkur === 'terlentang' && $umurBulan > 24) {
            $koreksi = -0.7;
        }

        return round($tinggiCm + $koreksi, 1);
    }

    public static function kategoriBbU(float $z): string
    {
        $ambang = config('nutrition.zscore.ambang.bb_u');

        if ($z < $ambang['sangat_kurang_max']) {
            return 'berat_badan_sangat_kurang';
        }
        if ($z < $ambang['kurang_max']) {
            return 'berat_badan_kurang';
        }
        if ($z <= $ambang['normal_max']) {
            return 'berat_badan_normal';
        }

        return 'risiko_berat_badan_lebih';
    }

    public static function kategoriTbU(float $z): string
    {
        $ambang = config('nutrition.zscore.ambang.tb_u');

        if ($z < $ambang['sangat_pendek_max']) {
            return 'sangat_pendek';
        }
        if ($z < $ambang['pendek_max']) {
            return 'pendek';
        }
        if ($z <= $ambang['normal_max']) {
            return 'normal';
        }

        return 'tinggi';
    }

    public static function kategoriBbTb(float $z): string
    {
        $ambang = config('nutrition.zscore.ambang.bb_tb');

        if ($z < $ambang['gizi_buruk_max']) {
            return 'gizi_buruk';
        }
        if ($z < $ambang['gizi_kurang_max']) {
            return 'gizi_kurang';
        }
        if ($z <= $ambang['normal_max']) {
            return 'gizi_baik';
        }
        if ($z <= $ambang['berisiko_lebih_max']) {
            return 'berisiko_gizi_lebih';
        }
        if ($z <= $ambang['gizi_lebih_max']) {
            return 'gizi_lebih';
        }

        return 'obesitas';
    }

    private function zDariUmur(string $indikator, string $jk, ?string $varian, int $umur, float $nilai): float
    {
        $std = WhoStandard::query()
            ->where('indikator', $indikator)
            ->where('jenis_kelamin', $jk)
            ->where('dasar', 'umur')
            ->where('varian', $varian)
            ->where('umur_bulan', $umur)
            ->first();

        if ($std === null) {
            throw ValidationException::withMessages([
                'tanggal_ukur' => ["Baku rujukan {$indikator} tidak tersedia untuk umur {$umur} bulan."],
            ]);
        }

        return self::zDariKurva($nilai, $std->sdCurve());
    }

    private function zDariPanjang(string $jk, string $varian, float $panjangCm, float $beratKg): float
    {
        $bawah = WhoStandard::query()
            ->where('indikator', WhoStandard::BB_TB)
            ->where('jenis_kelamin', $jk)
            ->where('dasar', 'panjang')
            ->where('varian', $varian)
            ->where('panjang_cm', '<=', $panjangCm)
            ->orderByDesc('panjang_cm')
            ->first();

        $atas = WhoStandard::query()
            ->where('indikator', WhoStandard::BB_TB)
            ->where('jenis_kelamin', $jk)
            ->where('dasar', 'panjang')
            ->where('varian', $varian)
            ->where('panjang_cm', '>=', $panjangCm)
            ->orderBy('panjang_cm')
            ->first();

        if ($bawah === null || $atas === null) {
            throw ValidationException::withMessages([
                'tinggi_badan_cm' => ['Tinggi badan di luar jangkauan tabel rujukan BB/TB.'],
            ]);
        }

        if ($bawah->id === $atas->id) {
            return self::zDariKurva($beratKg, $bawah->sdCurve());
        }

        // Interpolasi antar dua baris panjang terdekat per garis SD.
        $rentang = (float) $atas->panjang_cm - (float) $bawah->panjang_cm;
        $fraksi = $rentang > 0 ? ((float) $panjangCm - (float) $bawah->panjang_cm) / $rentang : 0.0;

        $kurvaBawah = $bawah->sdCurve();
        $kurvaAtas = $atas->sdCurve();
        $values = [];
        foreach ($kurvaBawah['values'] as $i => $v) {
            $values[] = $v + ($kurvaAtas['values'][$i] - $v) * $fraksi;
        }

        return self::zDariKurva($beratKg, ['thresholds' => $kurvaBawah['thresholds'], 'values' => $values]);
    }

    /**
     * @param  array{thresholds: list<float>, values: list<float>}  $kurva
     */
    public static function zDariKurva(float $nilai, array $kurva): float
    {
        [$t, $v] = [$kurva['thresholds'], $kurva['values']];
        $n = count($t);

        if ($nilai <= $v[0]) {
            return round($t[0] - ($v[0] - $nilai) / max($v[1] - $v[0], 1e-9), 2);
        }

        for ($i = 0; $i < $n - 1; $i++) {
            if ($nilai <= $v[$i + 1]) {
                $den = max($v[$i + 1] - $v[$i], 1e-9);

                return round($t[$i] + ($nilai - $v[$i]) / $den * ($t[$i + 1] - $t[$i]), 2);
            }
        }

        return round($t[$n - 1] + ($nilai - $v[$n - 1]) / max($v[$n - 1] - $v[$n - 2], 1e-9), 2);
    }
}
