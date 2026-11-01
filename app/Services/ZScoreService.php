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
    public const VERSI_ATURAN = 'permenkes-2-2020-baseline-v1';

    public const PESAN_NORMAL = 'Hasil ini bukan diagnosis. Lanjutkan pemantauan rutin dan konsultasikan dengan kader atau tenaga kesehatan bila diperlukan.';

    public const PESAN_AT_RISK = 'Hasil ini bukan diagnosis. Lakukan pemantauan lebih dekat dan konsultasikan dengan kader atau tenaga kesehatan.';

    public const PESAN_HIGH_RISK = 'Hasil ini bukan diagnosis. Segera konsultasikan dengan kader atau tenaga kesehatan sesuai alur rujukan.';

    public const PESAN_UMUM = 'Hasil ini bukan diagnosis. Konsultasikan dengan kader atau tenaga kesehatan bila diperlukan.';

    /**
     * @return array{umur_bulan: int, tinggi_koreksi_cm: float, z_bb_u: float, z_tb_u: float, z_bb_tb: float, status_bb_u: string, status_tb_u: string, status_bb_tb: string, status_gizi_antropometri: string, kategori_pemantauan: string, metode_klasifikasi: string, versi_aturan: string, pesan: string}
     */
    public function hitung(Anak $anak, float $beratKg, float $tinggiCm, string $tanggalUkur, ?string $posisiUkur): array
    {
        $umur = self::umurBulanPenuh($anak->tanggal_lahir->toDateString(), $tanggalUkur);

        if ($umur < 0 || $umur > 60) {
            throw ValidationException::withMessages([
                'tanggal_ukur' => ['Umur anak saat pengukuran di luar rentang 0-60 bulan.'],
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
            'status_gizi_antropometri' => self::agregat($statusBbU, $statusTbU, $statusBbTb),
            'kategori_pemantauan' => $kategori,
            'metode_klasifikasi' => Pertumbuhan::METODE_ACUAN_ATURAN,
            'versi_aturan' => self::VERSI_ATURAN,
            'pesan' => self::pesanKategori($kategori),
        ];
    }

    /**
     * Baseline kategori pemantauan (KONF-02 belum final).
     *
     * Kategori ini adalah rancangan sistem, bukan diagnosis WHO/Kemenkes.
     */
    public static function kategoriPemantauan(float $zBbU, float $zTbU, float $zBbTb): string
    {
        if ($zBbU < -3 || $zTbU < -3 || $zBbTb < -3) {
            return Pertumbuhan::KATEGORI_HIGH_RISK_REFERRAL;
        }

        if ($zBbU < -2 || $zTbU < -2 || $zBbTb < -2 || $zBbTb > 2 || $zBbU > 1) {
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
        if ($z < -3) {
            return 'berat_badan_sangat_kurang';
        }
        if ($z < -2) {
            return 'berat_badan_kurang';
        }
        if ($z <= 1) {
            return 'berat_badan_normal';
        }

        return 'risiko_berat_badan_lebih';
    }

    public static function kategoriTbU(float $z): string
    {
        if ($z < -3) {
            return 'sangat_pendek';
        }
        if ($z < -2) {
            return 'pendek';
        }
        if ($z <= 3) {
            return 'normal';
        }

        return 'tinggi';
    }

    public static function kategoriBbTb(float $z): string
    {
        if ($z < -3) {
            return 'gizi_buruk';
        }
        if ($z < -2) {
            return 'gizi_kurang';
        }
        if ($z <= 1) {
            return 'gizi_baik';
        }
        if ($z <= 2) {
            return 'berisiko_gizi_lebih';
        }
        if ($z <= 3) {
            return 'gizi_lebih';
        }

        return 'obesitas';
    }

    public static function agregat(string $statusBbU, string $statusTbU, string $statusBbTb): string
    {
        if ($statusBbTb === 'gizi_buruk') {
            return 'gizi_buruk';
        }

        if (in_array($statusTbU, ['sangat_pendek', 'pendek'], true)) {
            return 'stunting_terindikasi';
        }

        if ($statusBbTb === 'gizi_kurang'
            || in_array($statusBbU, ['berat_badan_sangat_kurang', 'berat_badan_kurang'], true)
        ) {
            return 'kurang';
        }

        if (in_array($statusBbU, ['risiko_berat_badan_lebih'], true)
            || in_array($statusBbTb, ['berisiko_gizi_lebih', 'gizi_lebih', 'obesitas'], true)
            || $statusTbU === 'tinggi'
        ) {
            return 'lebih';
        }

        return 'normal';
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
