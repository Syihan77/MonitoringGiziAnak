<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use Database\Seeders\WhoStandardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PertumbuhanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WhoStandardsSeeder::class);
    }

    /**
     * Autentikasi sebagai user via actingAs (bukan header Bearer) karena
     * guard Sanctum di-cache per instance aplikasi dalam satu test.
     */
    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    private function anakL24(User $owner): Anak
    {
        return Anak::create([
            'user_id' => $owner->id,
            'nama' => 'Budi',
            'tanggal_lahir' => '2024-01-15',
            'jenis_kelamin' => 'L',
        ]);
    }

    // SKPL UT-05: 24 bln, 12.5 kg, 85.2 cm -> normal.
    public function test_input_pertumbuhan_normal_201(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.umur_bulan', 24)
            ->assertJsonPath('data.kategori_pemantauan', 'NORMAL_LOW_RISK')
            ->assertJsonStructure(['data' => [
                'pertumbuhan_id', 'umur_bulan', 'zscore_bb_u', 'zscore_tb_u', 'zscore_bb_tb',
                'kategori_pemantauan', 'status_rincian',
            ]]);

        $this->assertEqualsWithDelta(0.21, (float) $res->json('data.zscore_bb_u'), 0.01);
        $this->assertEqualsWithDelta(-0.63, (float) $res->json('data.zscore_tb_u'), 0.01);
        $this->assertEqualsWithDelta(0.76, (float) $res->json('data.zscore_bb_tb'), 0.01);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'pertumbuhan_create']);
    }

    public function test_posisi_berdiri_24_bulan_dikoreksi_07(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);

        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
            'posisi_ukur' => 'berdiri',
        ], $this->as($ortu))->assertCreated();

        $this->assertDatabaseHas('pertumbuhans', [
            'anak_id' => $anak->id,
            'tinggi_koreksi_cm' => 85.9,
            'posisi_ukur' => 'berdiri',
        ]);
    }

    public function test_posisi_opsional_null_tanpa_koreksi(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);

        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu))->assertCreated();

        $this->assertDatabaseHas('pertumbuhans', [
            'anak_id' => $anak->id,
            'tinggi_koreksi_cm' => 85.2,
        ]);
    }

    public function test_validasi_input_400(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);

        // Berat negatif.
        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => -1,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu))->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        // Tanggal ukur sebelum lahir.
        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2024-01-14',
        ], $this->as($ortu))->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_umur_di_atas_60_bulan_ditolak(): void
    {
        $ortu = User::factory()->create();
        $anak = Anak::create([
            'user_id' => $ortu->id, 'nama' => 'Besar',
            'tanggal_lahir' => '2015-01-01', 'jenis_kelamin' => 'L',
        ]);

        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 20,
            'tinggi_badan_cm' => 110,
            'tanggal_ukur' => '2026-01-01',
        ], $this->as($ortu))->assertBadRequest();
    }

    public function test_kader_sewilayah_boleh_mencatat(): void
    {
        $ortu = User::factory()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $anak = $this->anakL24($ortu);
        $kader = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Melati']);

        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($kader))->assertCreated();
    }

    public function test_kader_lintas_wilayah_mencatat_ditolak(): void
    {
        $ortu = User::factory()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $anak = $this->anakL24($ortu);
        $kader = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Mawar']);

        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($kader))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_ortu_lain_ditolak_403(): void
    {
        $ortuA = User::factory()->create();
        $ortuB = User::factory()->create();
        $anak = $this->anakL24($ortuA);

        $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortuB))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_admin_tidak_boleh_mencatat_tetapi_boleh_melihat_riwayat(): void
    {
        $admin = User::factory()->admin()->create();
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);
        $h = $this->as($admin);
        $url = "/api/v1/anak/{$anak->id}/pertumbuhan";

        $this->postJson($url, [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $h)->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');

        $this->getJson($url, $h)->assertOk();
    }

    public function test_grafik_pertumbuhan_dengan_acuan_dan_filter(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);
        $h = $this->as($ortu);
        $url = "/api/v1/anak/{$anak->id}/tren-pertumbuhan";

        foreach (['2025-11-15', '2025-12-15', '2026-01-15'] as $tgl) {
            $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
                'berat_badan_kg' => 12.0,
                'tinggi_badan_cm' => 84.0,
                'tanggal_ukur' => $tgl,
            ], $h)->assertCreated();
        }

        $res = $this->getJson($url, $h)->assertOk();

        $this->assertCount(3, $res->json('data.titik'));
        $this->assertSame('2025-11-15', $res->json('data.titik.0.tanggal_ukur'));
        $this->assertArrayHasKey('kategori_pemantauan', $res->json('data.titik.0'));
        $this->assertArrayNotHasKey('status_bb_u', $res->json('data.titik.0'));
        $this->assertArrayNotHasKey('status_tb_u', $res->json('data.titik.0'));
        $this->assertArrayNotHasKey('status_bb_tb', $res->json('data.titik.0'));
        $this->assertCount(61, $res->json('data.acuan.bb_u'));
        $this->assertCount(61, $res->json('data.acuan.tb_u'));

        $filtered = $this->getJson(
            $url.'?dari_tanggal=2025-12-01&sampai_tanggal=2026-01-01',
            $h,
        )->assertOk();
        $this->assertCount(1, $filtered->json('data.titik'));
        $this->assertSame('2025-12-15', $filtered->json('data.titik.0.tanggal_ukur'));

        $this->getJson($url.'?dari_tanggal=bukan-tanggal', $h)
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_grafik_pertumbuhan_akses_dan_anak_tanpa_ukur(): void
    {
        $ortu = User::factory()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $anak = $this->anakL24($ortu);
        $kaderSama = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $kaderBeda = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Mawar']);
        $ortuLain = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $url = "/api/v1/anak/{$anak->id}/tren-pertumbuhan";

        $this->getJson($url, $this->as($kaderSama))->assertOk();
        $this->getJson($url, $this->as($admin))->assertOk();
        $this->getJson($url, $this->as($kaderBeda))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
        $this->getJson($url, $this->as($ortuLain))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');

        $res = $this->getJson($url, $this->as($ortu))->assertOk();
        $this->assertCount(0, $res->json('data.titik'));
        $this->assertCount(61, $res->json('data.acuan.bb_u'));
    }

    public function test_riwayat_dengan_filter_tanggal(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);
        $h = $this->as($ortu);
        $url = "/api/v1/anak/{$anak->id}/pertumbuhan";

        foreach (['2025-11-15', '2025-12-15', '2026-01-15'] as $tgl) {
            $this->postJson($url, [
                'berat_badan_kg' => 12.0, 'tinggi_badan_cm' => 84.0, 'tanggal_ukur' => $tgl,
            ], $h)->assertCreated();
        }

        $res = $this->getJson($url.'?dari_tanggal=2025-12-01&sampai_tanggal=2026-01-01', $h)->assertOk();
        $data = $res->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('2025-12-15', $data[0]['tanggal_ukur']);
    }

    public function test_riwayat_kosong_200(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anakL24($ortu);

        $this->getJson("/api/v1/anak/{$anak->id}/pertumbuhan", $this->as($ortu))
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }
}
