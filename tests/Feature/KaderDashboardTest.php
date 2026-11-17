<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\BahanMakanan;
use App\Models\Pertumbuhan;
use App\Models\User;
use Database\Seeders\WhoStandardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KaderDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WhoStandardsSeeder::class);
    }

    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    /**
     * @return array{anakA: Anak, anakB: Anak, anakC: Anak, anakD: Anak, anakE: Anak}
     */
    private function fixtures(): array
    {
        $kader = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $ortu1 = User::factory()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $ortu2 = User::factory()->create(['wilayah_posyandu' => 'Posyandu Mawar']);

        $buat = fn (User $o, string $nama) => Anak::create([
            'user_id' => $o->id, 'nama' => $nama,
            'tanggal_lahir' => '2024-01-15', 'jenis_kelamin' => 'L',
        ]);

        $anakA = $buat($ortu1, 'Anak Normal'); // 12.5/85.2 normal
        $anakB = $buat($ortu1, 'Anak Pendek'); // 8.7/75.0 sangat pendek
        $anakC = $buat($ortu1, 'Anak Tanpa Ukur'); // tanpa pengukuran
        $anakD = $buat($ortu2, 'Anak Mawar'); // wilayah lain
        $anakE = $buat($ortu1, 'Anak Kurus'); // 6.0/85.2 gizi buruk

        $ukur = function (Anak $anak, float $berat, float $tinggi, User $by) {
            $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
                'berat_badan_kg' => $berat,
                'tinggi_badan_cm' => $tinggi,
                'tanggal_ukur' => '2026-01-15',
            ], $this->as($by))->assertCreated();
        };

        $ukur($anakA, 12.5, 85.2, $ortu1);
        $ukur($anakB, 8.7, 75.0, $ortu1);
        $ukur($anakD, 12.5, 85.2, $ortu2);
        $ukur($anakE, 6.0, 85.2, $ortu1);

        return compact('anakA', 'anakB', 'anakC', 'anakD', 'anakE') + ['kader' => $kader, 'ortu1' => $ortu1];
    }

    // UT-10: dashboard wilayah sendiri menampilkan agregat.
    public function test_dashboard_wilayah_sendiri(): void
    {
        ['kader' => $kader] = $this->fixtures();

        $res = $this->getJson('/api/v1/kader/dashboard?wilayah=Posyandu%20Melati', $this->as($kader))
            ->assertOk()->assertJsonPath('success', true);

        $res->assertJsonPath('data.total_anak_binaan', 4)
            ->assertJsonPath('data.anak_terukur', 3)
            ->assertJsonPath('data.distribusi_kategori_pemantauan.NORMAL_LOW_RISK', 1)
            ->assertJsonPath('data.distribusi_kategori_pemantauan.AT_RISK_MONITORING', 0)
            ->assertJsonPath('data.distribusi_kategori_pemantauan.HIGH_RISK_REFERRAL', 2);
    }

    public function test_tren_prevalensi_memakai_kategori_dan_bucket_bulan_jakarta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01 00:30:00', 'Asia/Jakarta'));

        try {
            ['kader' => $kader, 'anakA' => $anakA, 'ortu1' => $ortu] = $this->fixtures();

            Pertumbuhan::create([
                'anak_id' => $anakA->id,
                'tanggal_ukur' => '2026-01-20',
                'umur_bulan' => 24,
                'berat_kg' => 12.5,
                'tinggi_cm' => 85.2,
                'tinggi_koreksi_cm' => 85.2,
                'status_bb_u' => 'berat_badan_normal',
                'status_tb_u' => 'normal',
                'status_bb_tb' => 'gizi_baik',
                'kategori_pemantauan' => Pertumbuhan::KATEGORI_AT_RISK_MONITORING,
                'dicatat_oleh' => $ortu->id,
            ]);

            $res = $this->getJson(
                '/api/v1/kader/dashboard?wilayah=Posyandu%20Melati&bulan=3',
                $this->as($kader),
            )->assertOk();

            $this->assertCount(3, $res->json('data.tren_prevalensi'));
            $this->assertSame('Des 2025', $res->json('data.tren_prevalensi.0.label'));
            $this->assertSame('Jan 2026', $res->json('data.tren_prevalensi.1.label'));
            $this->assertSame(3, $res->json('data.tren_prevalensi.1.total_diukur'));
            $this->assertSame(0, $res->json('data.tren_prevalensi.1.normal'));
            $this->assertSame(1, $res->json('data.tren_prevalensi.1.at_risk'));
            $this->assertSame(2, $res->json('data.tren_prevalensi.1.high_risk'));
            $this->assertSame(100.0, (float) $res->json('data.tren_prevalensi.1.persen_perlu_perhatian'));
            $this->assertTrue($res->json('data.tren_prevalensi.1.denominator_kecil'));

            $this->assertSame(0, $res->json('data.tren_prevalensi.2.total_diukur'));
            $this->assertNull($res->json('data.tren_prevalensi.2.persen_perlu_perhatian'));
            $this->assertFalse($res->json('data.tren_prevalensi.2.denominator_kecil'));

            $default = $this->getJson(
                '/api/v1/kader/dashboard?wilayah=Posyandu%20Melati',
                $this->as($kader),
            )->assertOk();
            $this->assertCount(6, $default->json('data.tren_prevalensi'));

            $this->getJson(
                '/api/v1/kader/dashboard?wilayah=Posyandu%20Melati&bulan=13',
                $this->as($kader),
            )->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_anonim_tanpa_nama(): void
    {
        ['kader' => $kader] = $this->fixtures();

        $res = $this->getJson('/api/v1/kader/dashboard?wilayah=Posyandu%20Melati', $this->as($kader))->assertOk();

        foreach (['Anak Normal', 'Anak Pendek', 'Anak Kurus', 'Anak Tanpa Ukur'] as $nama) {
            $this->assertStringNotContainsString($nama, $res->getContent());
        }
    }

    // UT-09: wilayah di luar binaan ditolak 403.
    public function test_dashboard_lintas_wilayah_403(): void
    {
        ['kader' => $kader] = $this->fixtures();

        $this->getJson('/api/v1/kader/dashboard?wilayah=Posyandu%20Mawar', $this->as($kader))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_dashboard_ortu_ditolak(): void
    {
        ['ortu1' => $ortu] = $this->fixtures();

        $this->getJson('/api/v1/kader/dashboard?wilayah=Posyandu%20Melati', $this->as($ortu))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_dashboard_tanpa_token_401(): void
    {
        $this->getJson('/api/v1/kader/dashboard?wilayah=Posyandu%20Melati')
            ->assertUnauthorized()->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_logbook_binaan_read_only(): void
    {
        ['kader' => $kader, 'anakA' => $anakA, 'anakD' => $anakD, 'ortu1' => $ortu] = $this->fixtures();

        $res = $this->getJson("/api/v1/kader/anak/{$anakA->id}/logbook", $this->as($kader))
            ->assertOk()
            ->assertJsonPath('data.nama', 'Anak Normal');
        $this->assertCount(1, $res->json('data.riwayat'));
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'logbook_view']);

        // Lintas wilayah ditolak.
        $this->getJson("/api/v1/kader/anak/{$anakD->id}/logbook", $this->as($kader))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');

        // Ortu tidak boleh lewat endpoint kader.
        $this->getJson("/api/v1/kader/anak/{$anakA->id}/logbook", $this->as($ortu))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');

        // Anak tidak ada.
        $this->getJson('/api/v1/kader/anak/00000000-0000-0000-0000-000000000000/logbook', $this->as($kader))
            ->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
    }

    public function test_logbook_memuat_konsumsi_ortu(): void
    {
        ['kader' => $kader, 'anakA' => $anakA, 'ortu1' => $ortu] = $this->fixtures();

        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Telur', 'kelompok_gizi' => 'Protein Hewani', 'protein_per100g' => 12,
        ]);

        $this->actingAs($ortu, 'sanctum');
        $this->postJson("/api/v1/anak/{$anakA->id}/kondisi-harian", [
            'tanggal' => today()->toDateString(),
            'status_kondisi' => 'TIDAK_SAKIT',
        ])->assertCreated();
        $this->postJson("/api/v1/anak/{$anakA->id}/konsumsi", [
            'tanggal_konsumsi' => today()->toDateString(),
            'metode_input' => 'MANUAL',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ])->assertCreated();

        $res = $this->getJson("/api/v1/kader/anak/{$anakA->id}/logbook", $this->as($kader))->assertOk();

        $this->assertCount(1, $res->json('data.konsumsi_terakhir'));
        $this->assertSame('Telur', $res->json('data.konsumsi_terakhir.0.items.0.nama_bahan'));
    }
}
