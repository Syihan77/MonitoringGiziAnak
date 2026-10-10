<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use Database\Seeders\WhoStandardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('data.distribusi_status_gizi.baik', 2)
            ->assertJsonPath('data.distribusi_status_gizi.sangat_kurang', 1)
            ->assertJsonPath('data.distribusi_stunting_indikatif.normal', 2)
            ->assertJsonPath('data.distribusi_stunting_indikatif.sangat_pendek', 1);
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

        $bahan = \App\Models\BahanMakanan::create([
            'nama_bahan' => 'Telur', 'kelompok_gizi' => 'Protein Hewani', 'protein_per100g' => 12,
        ]);

        $this->actingAs($ortu, 'sanctum');
        $this->postJson("/api/v1/anak/{$anakA->id}/konsumsi", [
            'tanggal_konsumsi' => today()->toDateString(),
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ])->assertCreated();

        $res = $this->getJson("/api/v1/kader/anak/{$anakA->id}/logbook", $this->as($kader))->assertOk();

        $this->assertCount(1, $res->json('data.konsumsi_terakhir'));
        $this->assertSame('Telur', $res->json('data.konsumsi_terakhir.0.items.0.nama_bahan'));
    }
}
