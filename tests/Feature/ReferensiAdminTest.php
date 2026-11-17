<?php

namespace Tests\Feature;

use App\Models\BahanMakanan;
use App\Models\Resep;
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

    private function resepLengkap(string $status = Resep::VERIFIKASI_DATA_LENGKAP): Resep
    {
        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Bahan Verifikasi',
            'kelompok_gizi' => 'Uji',
        ]);

        $resep = Resep::create([
            'nama_resep' => 'Resep Verifikasi',
            'cara_memasak' => 'Masak.',
            'kategori_gizi' => 'umum',
            'jumlah_porsi_hasil' => 1,
            'energi_per_porsi' => 10,
            'protein_per_porsi' => 10,
            'lemak_per_porsi' => 10,
            'karbohidrat_per_porsi' => 10,
            'serat_per_porsi' => 10,
            'kalsium_per_porsi' => 10,
            'zat_besi_per_porsi' => 10,
            'zinc_per_porsi' => 10,
            'vitamin_a_per_porsi' => 10,
            'vitamin_c_per_porsi' => 10,
            'usia_min_bulan' => 12,
            'usia_label_bulan' => 12,
            'alergen' => [],
            'status_verifikasi_data' => $status,
        ]);

        $resep->bahans()->create([
            'bahan_id' => $bahan->id,
            'takaran_jumlah' => 100,
            'takaran_satuan' => 'g',
            'takaran_gram_ekuivalen' => 100,
        ]);

        return $resep->load('bahans.bahan');
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
            'jumlah_porsi_hasil' => 1, 'bahans' => [],
        ], $h)->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_admin_crud_bahan_tanpa_harga(): void
    {
        $h = $this->asAdmin();

        $res = $this->postJson('/api/v1/bahan-makanan', [
            'nama_bahan' => 'Telur Ayam',
            'kelompok_gizi' => 'Protein Hewani',
            'protein_per100g' => 12,
            'sumber_rujukan' => 'TKPI',
        ], $h)->assertCreated();
        $id = $res->json('data.id');

        $this->assertArrayNotHasKey('estimasi_harga_per100g', $res->json('data'));

        // Field harga sudah dihapus dan harus ditolak.
        $this->postJson('/api/v1/bahan-makanan', [
            'nama_bahan' => 'Berharga',
            'kelompok_gizi' => 'X',
            'estimasi_harga_per100g' => 2500,
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

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

    public function test_pencarian_konsumsi_menyembunyikan_placeholder_dan_bahan_tanpa_gizi(): void
    {
        $h = $this->asAdmin();

        BahanMakanan::create([
            'nama_bahan' => 'Ayam, daging, segar',
            'kelompok_gizi' => 'Protein Hewani',
            'sumber_rujukan' => 'TKPI 2020 FR005',
            'energi_per100g' => 298,
            'protein_per100g' => 18.2,
            'lemak_per100g' => 25,
            'karbohidrat_per100g' => 0,
            'zat_besi_per100g' => 1.5,
            'zinc_per100g' => 1.3,
        ]);
        BahanMakanan::create([
            'nama_bahan' => 'Ayam fillet',
            'kelompok_gizi' => 'Dataset',
            'sumber_rujukan' => 'Dataset resep berlabel',
        ]);
        BahanMakanan::create([
            'nama_bahan' => 'Ayam tanpa gizi',
            'kelompok_gizi' => 'Uji',
        ]);

        $this->getJson('/api/v1/bahan-makanan?q=ayam&limit=20', $h)
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        $this->getJson('/api/v1/bahan-makanan?q=ayam&limit=20&untuk_konsumsi=1', $h)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.nama_bahan', 'Ayam, daging, segar');

        $this->getJson('/api/v1/bahan-makanan?q=ayam%20fillet&limit=20&untuk_konsumsi=1', $h)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.nama_bahan', 'Ayam, daging, segar');
    }

    public function test_bahan_terpakai_tak_bisa_dihapus(): void
    {
        $h = $this->asAdmin();
        $bahan = BahanMakanan::create(['nama_bahan' => 'A', 'kelompok_gizi' => 'X']);

        $resep = $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R', 'cara_memasak' => 'Masak.', 'kategori_gizi' => 'tinggi protein',
            'jumlah_porsi_hasil' => 1,
            'bahans' => [[
                'bahan_id' => $bahan->id,
                'takaran_jumlah' => 100,
                'takaran_satuan' => 'g',
                'takaran_gram_ekuivalen' => 100,
            ]],
        ], $h)->assertCreated();

        $this->deleteJson("/api/v1/bahan-makanan/{$bahan->id}", [], $h)
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        // Hapus resep dulu -> bahan bisa dihapus.
        $this->deleteJson("/api/v1/resep/{$resep->json('data.id')}", [], $h)->assertOk();
        $this->deleteJson("/api/v1/bahan-makanan/{$bahan->id}", [], $h)->assertOk();
    }

    public function test_admin_crud_resep_dan_field_biaya_ditolak(): void
    {
        $h = $this->asAdmin();
        $bahan = BahanMakanan::create(['nama_bahan' => 'Bahan Resep', 'kelompok_gizi' => 'X']);

        $res = $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R Pasti',
            'cara_memasak' => 'Masak.',
            'kategori_gizi' => 'tinggi protein',
            'jumlah_porsi_hasil' => 2,
            'tingkat_kesulitan' => 'Mudah',
            'bahans' => [[
                'bahan_id' => $bahan->id,
                'takaran_jumlah' => 100,
                'takaran_satuan' => 'g',
                'takaran_gram_ekuivalen' => 100,
            ]],
        ], $h)->assertCreated()
            ->assertJsonPath('data.jumlah_porsi_hasil', 2)
            ->assertJsonMissingPath('data.estimasi_biaya')
            ->assertJsonMissingPath('data.porsi_saji');

        $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R Berbiaya',
            'cara_memasak' => 'Masak.',
            'jumlah_porsi_hasil' => 1,
            'estimasi_biaya' => 2000,
            'bahans' => [[
                'bahan_id' => $bahan->id,
                'takaran_gram_ekuivalen' => 100,
            ]],
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R Tanpa Porsi',
            'cara_memasak' => 'Masak.',
            'bahans' => [[
                'bahan_id' => $bahan->id,
                'takaran_gram_ekuivalen' => 100,
            ]],
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->postJson('/api/v1/resep', [
            'nama_resep' => 'R Tanpa Satuan',
            'cara_memasak' => 'Masak.',
            'jumlah_porsi_hasil' => 1,
            'bahans' => [[
                'bahan_id' => $bahan->id,
                'takaran_gram_ekuivalen' => 100,
            ]],
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->putJson("/api/v1/resep/{$res->json('data.id')}", [
            'bahans' => [[
                'bahan_id' => $bahan->id,
                'takaran_jumlah' => 200,
                'takaran_satuan' => 'g',
                'takaran_gram_ekuivalen' => 200,
            ]],
        ], $h)->assertOk()->assertJsonPath('data.jumlah_porsi_hasil', 2);

        $this->getJson('/api/v1/resep?kategori_gizi=tinggi%20protein&limit=1', $h)
            ->assertOk()->assertJsonPath('data.per_page', 1);
    }

    public function test_verifikasi_resep_data_lengkap(): void
    {
        $h = $this->asAdmin();
        $resep = $this->resepLengkap();

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", [
            'status_verifikasi_data' => 'TERVERIFIKASI',
            'nama_pemverifikasi' => 'Ahli Gizi',
            'tanggal_verifikasi' => today()->toDateString(),
        ], $h)
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi_data', 'TERVERIFIKASI')
            ->assertJsonPath('data.nama_pemverifikasi', 'Ahli Gizi')
            ->assertJsonPath('data.tanggal_verifikasi', today()->toDateString());

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'resep_verify']);
    }

    public function test_verifikasi_wajib_field_dan_admin(): void
    {
        $resep = $this->resepLengkap();

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", [
            'status_verifikasi_data' => 'TERVERIFIKASI',
            'nama_pemverifikasi' => 'Ahli Gizi',
            'tanggal_verifikasi' => today()->toDateString(),
        ], $this->asOrtu())
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');

        $h = $this->asAdmin();

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", [], $h)
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", [
            'status_verifikasi_data' => 'TERVERIFIKASI',
            'nama_pemverifikasi' => 'Ahli Gizi',
            'tanggal_verifikasi' => today()->addDay()->toDateString(),
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_verifikasi_menolak_takaran_belum_lengkap(): void
    {
        $h = $this->asAdmin();
        $resep = $this->resepLengkap();

        $resep->bahans()->firstOrFail()->update([
            'takaran_satuan' => 'secukupnya',
            'takaran_gram_ekuivalen' => null,
        ]);

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", [
            'status_verifikasi_data' => 'TERVERIFIKASI',
            'nama_pemverifikasi' => 'Ahli Gizi',
            'tanggal_verifikasi' => today()->toDateString(),
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_verifikasi_menolak_dan_menurunkan_resep_tanpa_empat_makro(): void
    {
        $h = $this->asAdmin();
        $resep = $this->resepLengkap();
        $resep->update([
            'energi_per_porsi' => null,
            'karbohidrat_per_porsi' => null,
            'protein_per_porsi' => null,
            'lemak_per_porsi' => null,
        ]);

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", [
            'status_verifikasi_data' => 'TERVERIFIKASI',
            'nama_pemverifikasi' => 'Ahli Gizi',
            'tanggal_verifikasi' => today()->toDateString(),
        ], $h)->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $resep->refresh();
        $this->assertSame(Resep::VERIFIKASI_PERLU_VERIFIKASI, $resep->status_verifikasi_data);
        $this->assertStringContainsString(Resep::CATATAN_MAKRO_TIDAK_LENGKAP, (string) $resep->catatan_resep);
    }

    public function test_edit_makro_menjadi_null_menurunkan_status_dengan_catatan(): void
    {
        $h = $this->asAdmin();
        $resep = $this->resepLengkap();

        $this->putJson("/api/v1/resep/{$resep->id}", [
            'energi_per_porsi' => null,
        ], $h)
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi_data', Resep::VERIFIKASI_PERLU_VERIFIKASI);

        $resep->refresh();
        $this->assertStringContainsString(Resep::CATATAN_MAKRO_TIDAK_LENGKAP, (string) $resep->catatan_resep);
        $this->assertSame(1, substr_count((string) $resep->catatan_resep, Resep::CATATAN_MAKRO_TIDAK_LENGKAP));
    }

    public function test_edit_substantif_mereset_verifikasi(): void
    {
        $h = $this->asAdmin();
        $resep = $this->resepLengkap();

        $verifikasi = [
            'status_verifikasi_data' => 'TERVERIFIKASI',
            'nama_pemverifikasi' => 'Ahli Gizi',
            'tanggal_verifikasi' => today()->toDateString(),
        ];

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", $verifikasi, $h)->assertOk();

        $this->putJson("/api/v1/resep/{$resep->id}", ['jumlah_porsi_hasil' => 2], $h)
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi_data', Resep::VERIFIKASI_DATA_LENGKAP)
            ->assertJsonPath('data.nama_pemverifikasi', null)
            ->assertJsonPath('data.tanggal_verifikasi', null);

        $this->patchJson("/api/v1/admin/resep/{$resep->id}/status", $verifikasi, $h)->assertOk();
        $this->putJson("/api/v1/resep/{$resep->id}", ['catatan_resep' => 'Catatan baru'], $h)
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi_data', Resep::VERIFIKASI_TERVERIFIKASI);
    }
}
