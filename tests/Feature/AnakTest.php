<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Autentikasi sebagai user via actingAs (bukan header Bearer) karena
     * guard Sanctum di-cache per instance aplikasi dalam satu test.
     */
    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    private function anakPayload(array $overrides = []): array
    {
        return array_merge([
            'nama' => 'Budi Santoso',
            'tanggal_lahir' => '2024-01-15',
            'jenis_kelamin' => 'L',
            'alamat' => 'Jl. Melati No. 1',
        ], $overrides);
    }

    public function test_ortu_buat_anak_201(): void
    {
        $ortu = User::factory()->create();

        $res = $this->postJson('/api/v1/anak', $this->anakPayload(), $this->as($ortu));

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama', 'Budi Santoso')
            ->assertJsonPath('data.jenis_kelamin', 'L')
            ->assertJsonStructure(['data' => ['anak_id', 'nama', 'tanggal_lahir', 'usia_bulan', 'jenis_kelamin']]);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'anak_create']);
    }

    public function test_buat_anak_tanpa_alamat_tetap_berhasil(): void
    {
        $ortu = User::factory()->create();
        $payload = $this->anakPayload();
        unset($payload['alamat']);

        $this->postJson('/api/v1/anak', $payload, $this->as($ortu))->assertCreated();
    }

    public function test_buat_anak_invalid_400_validation_error(): void
    {
        $ortu = User::factory()->create();

        $this->postJson('/api/v1/anak', $this->anakPayload(['tanggal_lahir' => '2030-01-01']), $this->as($ortu))
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->postJson('/api/v1/anak', $this->anakPayload(['jenis_kelamin' => 'X']), $this->as($ortu))
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_ortu_hanya_melihat_anak_sendiri(): void
    {
        $ortuA = User::factory()->create();
        $ortuB = User::factory()->create();
        Anak::create([...$this->anakPayload(), 'user_id' => $ortuA->id]);
        Anak::create([...$this->anakPayload(['nama' => 'Milik B']), 'user_id' => $ortuB->id]);

        $res = $this->getJson('/api/v1/anak', $this->as($ortuA))->assertOk();

        $this->assertCount(1, $res->json('data.data'));
        $this->assertSame('Budi Santoso', $res->json('data.data.0.nama'));
    }

    public function test_kader_melihat_anak_sewilayah_tapi_ditolak_lintas_wilayah(): void
    {
        $ortu = User::factory()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $anak = Anak::create([...$this->anakPayload(), 'user_id' => $ortu->id]);

        $kaderSama = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $kaderBeda = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Mawar']);

        $this->getJson("/api/v1/anak/{$anak->id}", $this->as($kaderSama))->assertOk();
        $this->getJson("/api/v1/anak/{$anak->id}", $this->as($kaderBeda))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_ortu_lain_ditolak_melihat_anak(): void
    {
        $ortuA = User::factory()->create();
        $ortuB = User::factory()->create();
        $anak = Anak::create([...$this->anakPayload(), 'user_id' => $ortuA->id]);

        $this->getJson("/api/v1/anak/{$anak->id}", $this->as($ortuB))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_update_oleh_pemilik_berhasil(): void
    {
        $ortu = User::factory()->create();
        $anak = Anak::create([...$this->anakPayload(), 'user_id' => $ortu->id]);

        $this->putJson("/api/v1/anak/{$anak->id}", ['nama' => 'Budi Baru'], $this->as($ortu))
            ->assertOk()->assertJsonPath('data.nama', 'Budi Baru');
    }

    public function test_update_oleh_bukan_pemilik_ditolak(): void
    {
        $ortuA = User::factory()->create();
        $ortuB = User::factory()->create();
        $anak = Anak::create([...$this->anakPayload(), 'user_id' => $ortuA->id]);

        $this->putJson("/api/v1/anak/{$anak->id}", ['nama' => 'X'], $this->as($ortuB))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_hapus_oleh_pemilik_berhasil(): void
    {
        $ortu = User::factory()->create();
        $anak = Anak::create([...$this->anakPayload(), 'user_id' => $ortu->id]);

        $this->deleteJson("/api/v1/anak/{$anak->id}", [], $this->as($ortu))->assertOk();
        $this->assertDatabaseMissing('anaks', ['id' => $anak->id]);
    }

    public function test_detail_anak_tidak_ada_404(): void
    {
        $ortu = User::factory()->create();

        $this->getJson('/api/v1/anak/00000000-0000-0000-0000-000000000000', $this->as($ortu))
            ->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
    }

    public function test_tanpa_token_401(): void
    {
        $this->getJson('/api/v1/anak')
            ->assertUnauthorized()->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    // SKPL §5.4.1: ?limit default 20, maks 100.
    public function test_paginasi_limit(): void
    {
        $ortu = User::factory()->create();
        foreach (['A', 'B', 'C'] as $nama) {
            Anak::create([...$this->anakPayload(['nama' => "Anak {$nama}"]), 'user_id' => $ortu->id]);
        }

        $res = $this->getJson('/api/v1/anak?limit=2', $this->as($ortu))->assertOk();
        $this->assertCount(2, $res->json('data.data'));
        $this->assertSame(2, $res->json('data.per_page'));
        $this->assertSame(3, $res->json('data.total'));

        $this->getJson('/api/v1/anak?limit=200', $this->as($ortu))
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_ringkas_ukur_dan_pemilik(): void
    {
        $ortu = User::factory()->create(['nama' => 'Ibu Ratna', 'wilayah_posyandu' => 'Posyandu Melati']);
        $anak = Anak::create([...$this->anakPayload(), 'user_id' => $ortu->id]);

        // Tanpa flag: tanpa ukur_terakhir.
        $res = $this->getJson('/api/v1/anak', $this->as($ortu))->assertOk();
        $this->assertSame('Ibu Ratna', $res->json('data.data.0.pemilik_nama'));
        $this->assertSame('Posyandu Melati', $res->json('data.data.0.wilayah'));
        $this->assertArrayNotHasKey('ukur_terakhir', $res->json('data.data.0'));

        // Dengan flag, belum ada ukur -> null.
        $res = $this->getJson('/api/v1/anak?ringkas_ukur=1', $this->as($ortu))->assertOk();
        $this->assertNull($res->json('data.data.0.ukur_terakhir'));

        // Dengan ukur -> terisi.
        \App\Models\Pertumbuhan::create([
            'anak_id' => $anak->id, 'tanggal_ukur' => '2026-01-15', 'umur_bulan' => 24,
            'berat_kg' => 12.5, 'tinggi_cm' => 85.2, 'tinggi_koreksi_cm' => 85.2,
            'z_bb_u' => 0.21, 'z_tb_u' => -0.63, 'z_bb_tb' => 0.76,
            'status_bb_u' => 'berat_badan_normal', 'status_tb_u' => 'normal',
            'status_bb_tb' => 'gizi_baik', 'status_gizi_antropometri' => 'normal',
            'dicatat_oleh' => $ortu->id,
        ]);

        $res = $this->getJson('/api/v1/anak?ringkas_ukur=1', $this->as($ortu))->assertOk();
        $this->assertSame('2026-01-15', $res->json('data.data.0.ukur_terakhir.tanggal_ukur'));
        $this->assertSame('normal', $res->json('data.data.0.ukur_terakhir.status_gizi_antropometri'));
    }
}
