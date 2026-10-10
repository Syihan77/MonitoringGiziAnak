<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\BahanMakanan;
use App\Models\Resep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekomendasiTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    private function bahan(string $nama, float $protein, float $besi, ?float $harga): BahanMakanan
    {
        return BahanMakanan::create([
            'nama_bahan' => $nama,
            'kelompok_gizi' => 'Uji',
            'protein_per100g' => $protein,
            'zat_besi_per100g' => $besi,
            'estimasi_harga_per100g' => $harga,
        ]);
    }

    private function resep(string $nama, string $kategori, array $bahans): Resep
    {
        $resep = Resep::create([
            'nama_resep' => $nama,
            'cara_memasak' => 'Masak.',
            'kategori_gizi' => $kategori,
            'porsi_saji' => 1,
        ]);

        foreach ($bahans as [$bahan, $gram]) {
            $resep->bahans()->create(['bahan_id' => $bahan->id, 'takaran_gram' => $gram]);
        }

        $resep->update(['estimasi_biaya' => $resep->hitungUlangBiaya()]);

        return $resep;
    }

    /**
     * @return array{ortu: User, anak: Anak, a: BahanMakanan, b: BahanMakanan, c: BahanMakanan, r1: Resep, r2: Resep}
     */
    private function fixtures(): array
    {
        $ortu = User::factory()->create();
        $anak = Anak::create([
            'user_id' => $ortu->id, 'nama' => 'Budi',
            'tanggal_lahir' => '2024-01-15', 'jenis_kelamin' => 'L',
        ]);

        $a = $this->bahan('Bahan A', 10, 0, 5000);
        $b = $this->bahan('Bahan B', 0, 5, 3000);
        $c = $this->bahan('Bahan C', 5, 5, 50000);
        $d = $this->bahan('Bahan D Tanpa Harga', 5, 5, null);

        // R1: [A 100g, B 50g] biaya 6500, protein relevan.
        $r1 = $this->resep('R1 Protein', 'tinggi protein', [[$a, 100], [$b, 50]]);
        // R2: [B 100g, C 10g] biaya 8000.
        $r2 = $this->resep('R2 Besi', 'tinggi zat besi', [[$b, 100], [$c, 10]]);
        // R3: tanpa info harga -> jujur dilewati pada mode anggaran.
        $this->resep('R3 Misteri', 'tinggi protein', [[$d, 100]]);

        // Konsumsi: A 100g -> protein 10g/20g = 50% (defisiensi).
        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'items' => [['bahan_id' => $a->id, 'porsi_gram' => 100]],
        ], $this->as($ortu))->assertCreated();

        return compact('ortu', 'anak', 'a', 'b', 'c', 'r1', 'r2');
    }

    // UT-07: anggaran di bawah resep termurah -> kosong + saran.
    public function test_anggaran_terlalu_rendah_kosong_dengan_saran(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'anggaran_max' => 1000,
            'tanggal' => '2026-01-15',
        ], $this->as($ortu))->assertCreated();

        $res->assertJsonPath('data.jumlah_rekomendasi', 0)
            ->assertJsonCount(0, 'data.menu');
        $this->assertNotNull($res->json('data.saran'));
        $this->assertSame(1, $res->json('data.tanpa_info_biaya'));
        $this->assertDatabaseHas('rekomendasi_logs', ['anak_id' => $anak->id, 'jumlah_hasil' => 0]);
    }

    // UT-08: terurut kecocokan + relevan defisiensi.
    public function test_mode_bahan_terurut_kecocokan(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'a' => $a, 'b' => $b] = $this->fixtures();

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'bahan_tersedia' => [$a->id, $b->id],
            'anggaran_max' => 20000,
            'tanggal' => '2026-01-15',
        ], $this->as($ortu))->assertCreated();

        $menu = $res->json('data.menu');
        $this->assertSame(2, $res->json('data.jumlah_rekomendasi'));
        $this->assertSame('R1 Protein', $menu[0]['nama_resep']);
        $this->assertSame('100%', $menu[0]['kecocokan_bahan']);
        $this->assertSame('R2 Besi', $menu[1]['nama_resep']);
        $this->assertSame('50%', $menu[1]['kecocokan_bahan']);
        $this->assertContains('protein', $res->json('data.defisiensi_terdeteksi'));
    }

    public function test_tanpa_masukan_400_dan_idor_403(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $lain = User::factory()->create();

        $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [], $this->as($ortu))
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'anggaran_max' => 20000,
        ], $this->as($lain))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_detail_resep_dan_404(): void
    {
        ['ortu' => $ortu, 'r1' => $r1] = $this->fixtures();

        $res = $this->getJson("/api/v1/resep/{$r1->id}", $this->as($ortu))
            ->assertOk()->assertJsonPath('data.nama_resep', 'R1 Protein');

        $this->assertCount(2, $res->json('data.bahans'));
        $this->assertEqualsWithDelta(6500.0, (float) $res->json('data.estimasi_biaya'), 0.01);
        $this->assertTrue($res->json('data.biaya_lengkap'));
        $this->assertEqualsWithDelta(10.0, (float) $res->json('data.gizi_per_porsi.protein'), 0.01);
        $this->assertArrayHasKey('energi', $res->json('data.gizi_per_porsi'));

        $this->getJson('/api/v1/resep/00000000-0000-0000-0000-000000000000', $this->as($ortu))
            ->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
    }
}
