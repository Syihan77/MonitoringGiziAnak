<?php

namespace Tests\Feature;

use App\Models\BahanMakanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferensiAdminTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): array
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');

        return [];
    }

    private function asOrtu(): array
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        return [];
    }

    // UT-12: non-admin ditolak menulis referensi.
    public function test_non_admin_tulis_ditolak(): void
    {
        $h = $this->asOrtu();

        $this->postJson('/api/v1/bahan-makanan', [
            'nama_bahan' => 'X', 'kelompok_gizi' => 'Y',
        ], $h)->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');

        $this->postJson('/api/v1/resep', [
            'nama_resep' => 'X', 'cara_memasak' => 'Y', 'kategori_gizi' => 'Z',
            'porsi_saji' => 1, 'bahans' => [],
        ], $h)->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_admin_crud_bahan(): void
    {
        $h = $this->asAdmin();

        $res = $this->postJson('/api/v1/bahan-makanan', [
            'nama_bahan' => 'Telur Ayam',
            'kelompok_gizi' => 'Protein Hewani',
            'protein_per100g' => 12,
            'estimasi_harga_per100g' => 2500,
            'sumber_rujukan' => 'TKPI',
        ], $h)->assertCreated();
        $id = $res->json('data.id');

        // Nilai negatif ditolak (F-11).
        $this->postJson('/api/v1/bahan-makanan', [
            'nama_bahan' => 'Buruk', 'kelompok_gizi' => 'X', 'protein_per100g' => -1,
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        // Pencarian + limit.
        $this->getJson('/api/v1/bahan-makanan?q=telur&limit=5', $h)
            ->assertOk()->assertJsonPath('data.total', 1);

        $this->putJson("/api/v1/bahan-makanan/{$id}", ['protein_per100g' => 13], $h)
            ->assertOk()->assertJsonPath('data.protein_per100g', 13);

        $this->deleteJson("/api/v1/bahan-makanan/{$id}", [], $h)->assertOk();
        $this->assertDatabaseMissing('bahan_makanans', ['id' => $id]);
    }

    public function test_bahan_terpakai_tak_bisa_dihapus(): void
    {
        $h = $this->asAdmin();
        $bahan = BahanMakanan::create(['nama_bahan' => 'A', 'kelompok_gizi' => 'X']);

        $resep = $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R', 'cara_memasak' => 'Masak.', 'kategori_gizi' => 'tinggi protein',
            'porsi_saji' => 1,
            'bahans' => [['bahan_id' => $bahan->id, 'takaran_gram' => 100]],
        ], $h)->assertCreated();

        $this->deleteJson("/api/v1/bahan-makanan/{$bahan->id}", [], $h)
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        // Hapus resep dulu -> bahan bisa dihapus.
        $this->deleteJson("/api/v1/resep/{$resep->json('data.id')}", [], $h)->assertOk();
        $this->deleteJson("/api/v1/bahan-makanan/{$bahan->id}", [], $h)->assertOk();
    }

    public function test_admin_crud_resep_biaya_otomatis(): void
    {
        $h = $this->asAdmin();
        $murah = BahanMakanan::create([
            'nama_bahan' => 'Murah', 'kelompok_gizi' => 'X', 'estimasi_harga_per100g' => 2000,
        ]);
        $tanpaHarga = BahanMakanan::create(['nama_bahan' => 'Misteri', 'kelompok_gizi' => 'X']);

        // Biaya = 100/100*2000 = 2000.
        $res = $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R Pasti', 'cara_memasak' => 'Masak.', 'kategori_gizi' => 'tinggi protein',
            'porsi_saji' => 2, 'tingkat_kesulitan' => 'Mudah',
            'bahans' => [['bahan_id' => $murah->id, 'takaran_gram' => 100]],
        ], $h)->assertCreated();
        $this->assertEqualsWithDelta(2000.0, (float) $res->json('data.estimasi_biaya'), 0.01);

        // Ada bahan tanpa harga -> biaya NULL.
        $res2 = $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R Misteri', 'cara_memasak' => 'Masak.', 'kategori_gizi' => 'tinggi protein',
            'porsi_saji' => 1,
            'bahans' => [
                ['bahan_id' => $murah->id, 'takaran_gram' => 100],
                ['bahan_id' => $tanpaHarga->id, 'takaran_gram' => 50],
            ],
        ], $h)->assertCreated();
        $this->assertNull($res2->json('data.estimasi_biaya'));

        // Update takaran -> biaya dihitung ulang (200g -> 4000).
        $this->putJson("/api/v1/resep/{$res->json('data.id')}", [
            'bahans' => [['bahan_id' => $murah->id, 'takaran_gram' => 200]],
        ], $h)->assertOk()->assertJsonPath('data.estimasi_biaya', 4000);

        // Filter kategori + limit.
        $this->getJson('/api/v1/resep?kategori_gizi=tinggi%20protein&limit=1', $h)
            ->assertOk()->assertJsonPath('data.per_page', 1);
    }
}
