<?php

namespace Tests\Unit;

use App\Models\Anak;
use App\Models\User;
use App\Models\WhoStandard;
use App\Services\ZScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ZScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedMinimalWho(): void
    {
        // BB/U L 24 (nilai asli Permenkes Tabel 1).
        WhoStandard::create([
            'indikator' => 'bb_u', 'jenis_kelamin' => 'L', 'dasar' => 'umur', 'varian' => null,
            'umur_bulan' => 24, 'sd_minus3' => 8.6, 'sd_minus2' => 9.7, 'sd_minus1' => 10.8,
            'median' => 12.2, 'sd_plus1' => 13.6, 'sd_plus2' => 15.3, 'sd_plus3' => 17.1,
        ]);
        // TB/U L 24 varian tb (nilai asli Tabel 3).
        WhoStandard::create([
            'indikator' => 'tb_u', 'jenis_kelamin' => 'L', 'dasar' => 'umur', 'varian' => 'tb',
            'umur_bulan' => 24, 'sd_minus3' => 78.0, 'sd_minus2' => 81.0, 'sd_minus1' => 84.1,
            'median' => 87.1, 'sd_plus1' => 90.2, 'sd_plus2' => 93.2, 'sd_plus3' => 96.3,
        ]);
        // BB/TB L 85.0 & 85.5 varian tb (nilai asli Tabel 5).
        // Baris 75.x sintetis (kurva sejajar, hanya utk uji agregat stunting).
        foreach ([[85.0, 9.2, 10.0, 10.8, 11.7, 12.7, 13.8, 15.1], [85.5, 9.3, 10.1, 10.9, 11.8, 12.8, 13.9, 15.2], [75.0, 6.8, 7.4, 8.0, 8.7, 9.5, 10.3, 11.3], [75.5, 6.9, 7.5, 8.1, 8.8, 9.6, 10.4, 11.4]] as [$cm, $m3, $m2, $m1, $med, $p1, $p2, $p3]) {
            WhoStandard::create([
                'indikator' => 'bb_tb', 'jenis_kelamin' => 'L', 'dasar' => 'panjang', 'varian' => 'tb',
                'panjang_cm' => $cm, 'sd_minus3' => $m3, 'sd_minus2' => $m2, 'sd_minus1' => $m1,
                'median' => $med, 'sd_plus1' => $p1, 'sd_plus2' => $p2, 'sd_plus3' => $p3,
            ]);
        }
    }

    public function test_umur_bulan_penuh_membulat_ke_bawah(): void
    {
        // 2 bulan 29 hari -> 2 (aturan Permenkes).
        $this->assertSame(2, ZScoreService::umurBulanPenuh('2026-01-01', '2026-03-30'));
        $this->assertSame(24, ZScoreService::umurBulanPenuh('2024-01-15', '2026-01-15'));
        $this->assertSame(0, ZScoreService::umurBulanPenuh('2026-01-15', '2026-01-20'));
    }

    public function test_umur_tanggal_ukur_sebelum_lahir_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        ZScoreService::umurBulanPenuh('2026-01-15', '2026-01-14');
    }

    public function test_koreksi_posisi_07cm(): void
    {
        $this->assertSame(85.9, ZScoreService::koreksiTinggi(24, 85.2, 'berdiri'));
        $this->assertSame(84.5, ZScoreService::koreksiTinggi(30, 85.2, 'terlentang'));
        // Posisi opsional: null = tanpa koreksi.
        $this->assertSame(85.2, ZScoreService::koreksiTinggi(24, 85.2, null));
        // Posisi sudah sesuai: tanpa koreksi.
        $this->assertSame(85.2, ZScoreService::koreksiTinggi(30, 85.2, 'berdiri'));
        $this->assertSame(60.0, ZScoreService::koreksiTinggi(10, 60.0, 'terlentang'));
    }

    public function test_z_dari_kurva_interpolasi_dan_ekstrapolasi(): void
    {
        $kurva = ['thresholds' => [-3.0, -2.0, -1.0, 0.0, 1.0, 2.0, 3.0], 'values' => [8.6, 9.7, 10.8, 12.2, 13.6, 15.3, 17.1]];

        $this->assertSame(0.0, ZScoreService::zDariKurva(12.2, $kurva));
        $this->assertSame(-2.0, ZScoreService::zDariKurva(9.7, $kurva));
        // Titik tengah median..+1SD -> 0.5.
        $this->assertSame(0.5, ZScoreService::zDariKurva(12.9, $kurva));
        // Di bawah -3SD diekstrapolasi linear (negatif).
        $this->assertLessThan(-3.0, ZScoreService::zDariKurva(8.0, $kurva));
        $this->assertGreaterThan(3.0, ZScoreService::zDariKurva(18.0, $kurva));
    }

    public function test_kategori_batas_ambang_permenkes(): void
    {
        $this->assertSame('berat_badan_kurang', ZScoreService::kategoriBbU(-3.0));
        $this->assertSame('berat_badan_sangat_kurang', ZScoreService::kategoriBbU(-3.01));
        $this->assertSame('berat_badan_normal', ZScoreService::kategoriBbU(-2.0));
        $this->assertSame('berat_badan_normal', ZScoreService::kategoriBbU(1.0));
        $this->assertSame('risiko_berat_badan_lebih', ZScoreService::kategoriBbU(1.01));

        $this->assertSame('pendek', ZScoreService::kategoriTbU(-3.0));
        $this->assertSame('sangat_pendek', ZScoreService::kategoriTbU(-3.01));
        $this->assertSame('normal', ZScoreService::kategoriTbU(3.0));
        $this->assertSame('tinggi', ZScoreService::kategoriTbU(3.01));

        $this->assertSame('berisiko_gizi_lebih', ZScoreService::kategoriBbTb(2.0));
        $this->assertSame('gizi_lebih', ZScoreService::kategoriBbTb(2.01));
        $this->assertSame('obesitas', ZScoreService::kategoriBbTb(3.01));
    }

    public function test_hitung_anak_normal_24_bulan(): void
    {
        $this->seedMinimalWho();

        $anak = Anak::create([
            'user_id' => User::factory()->create()->id,
            'nama' => 'Budi',
            'tanggal_lahir' => '2024-01-15',
            'jenis_kelamin' => 'L',
        ]);

        $hasil = app(ZScoreService::class)->hitung($anak, 12.5, 85.2, '2026-01-15', null);

        $this->assertSame(24, $hasil['umur_bulan']);
        $this->assertEqualsWithDelta(0.21, $hasil['z_bb_u'], 0.01);
        $this->assertEqualsWithDelta(-0.63, $hasil['z_tb_u'], 0.01);
        $this->assertEqualsWithDelta(0.76, $hasil['z_bb_tb'], 0.01);
        $this->assertSame('normal', $hasil['status_gizi_antropometri']);
    }

    public function test_hitung_stunting_terindikasi(): void
    {
        $this->seedMinimalWho();

        $anak = Anak::create([
            'user_id' => User::factory()->create()->id,
            'nama' => 'Ani',
            'tanggal_lahir' => '2024-01-15',
            'jenis_kelamin' => 'L',
        ]);

        // Tinggi jauh di bawah -3SD (78.0) -> sangat_pendek.
        $hasil = app(ZScoreService::class)->hitung($anak, 8.7, 75.0, '2026-01-15', null);

        $this->assertSame('sangat_pendek', $hasil['status_tb_u']);
        $this->assertSame('stunting_terindikasi', $hasil['status_gizi_antropometri']);
    }
}
