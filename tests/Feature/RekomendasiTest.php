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

    private function bahan(string $nama, float $protein = 5, float $besi = 2): BahanMakanan
    {
        return BahanMakanan::create([
            'nama_bahan' => $nama,
            'kelompok_gizi' => 'Uji',
            'protein_per100g' => $protein,
            'zat_besi_per100g' => $besi,
        ]);
    }

    private function resep(
        string $nama,
        string $kategori,
        array $bahans,
        string $status = Resep::VERIFIKASI_TERVERIFIKASI,
        string $caraMemasak = 'Masak.',
    ): Resep {
        $resep = Resep::create([
            'nama_resep' => $nama,
            'cara_memasak' => $caraMemasak,
            'kategori_gizi' => $kategori,
            'jumlah_porsi_hasil' => 1,
            'energi_per_porsi' => 300,
            'protein_per_porsi' => 12,
            'lemak_per_porsi' => 8,
            'karbohidrat_per_porsi' => 40,
            'serat_per_porsi' => 4,
            'kalsium_per_porsi' => 100,
            'zat_besi_per_porsi' => 3,
            'zinc_per_porsi' => 2,
            'vitamin_a_per_porsi' => 200,
            'vitamin_c_per_porsi' => 10,
            'usia_min_bulan' => 6,
            'usia_maks_bulan' => null,
            'status_verifikasi_data' => $status,
            'alergen' => [],
        ]);

        foreach ($bahans as [$bahan, $gram]) {
            $resep->bahans()->create([
                'bahan_id' => $bahan->id,
                'takaran_jumlah' => $gram,
                'takaran_satuan' => 'g',
                'takaran_gram_ekuivalen' => $gram,
            ]);
        }

        return $resep;
    }

    /**
     * @return array{ortu: User, anak: Anak, a: BahanMakanan, b: BahanMakanan, c: BahanMakanan, r1: Resep, r2: Resep, r4: Resep}
     */
    private function fixtures(): array
    {
        $ortu = User::factory()->create();
        $anak = Anak::create([
            'user_id' => $ortu->id, 'nama' => 'Budi',
            'tanggal_lahir' => '2024-01-15', 'jenis_kelamin' => 'L',
        ]);

        $a = $this->bahan('Bahan A', 10, 0);
        $b = $this->bahan('Bahan B', 0, 5);
        $c = $this->bahan('Bahan C', 5, 5);

        $r1 = $this->resep('R1 Protein', 'tinggi protein', [[$a, 100], [$b, 50]]);
        $r2 = $this->resep('R2 Besi', 'tinggi zat besi', [[$b, 100], [$c, 10]]);

        $this->resep('R3 Perlu Verifikasi', 'tinggi protein', [[$a, 100]], Resep::VERIFIKASI_PERLU_VERIFIKASI);
        $r4 = $this->resep('R4 Data Lengkap', 'tinggi protein', [[$c, 100]], Resep::VERIFIKASI_DATA_LENGKAP);

        return compact('ortu', 'anak', 'a', 'b', 'c', 'r1', 'r2', 'r4');
    }

    public function test_anggaran_max_ditolak(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();

        $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'anggaran_max' => 1000,
            'tanggal' => '2026-01-15',
        ], $this->as($ortu))
            ->assertBadRequest()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_mode_bahan_menyaring_dan_menghitung_kecocokan(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'a' => $a, 'b' => $b] = $this->fixtures();

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'bahan_tersedia' => [$a->id, $b->id],
            'tanggal' => '2026-01-15',
        ], $this->as($ortu))->assertOk();

        $menu = collect($res->json('data.menu'))->keyBy('nama_resep');

        $this->assertSame(2, $res->json('data.jumlah_rekomendasi'));
        $this->assertFalse($menu->has('R3 Perlu Verifikasi'));
        $this->assertFalse($menu->has('R4 Data Lengkap'));
        $this->assertSame('100%', $menu['R1 Protein']['kecocokan_bahan']);
        $this->assertSame('50%', $menu['R2 Besi']['kecocokan_bahan']);
        $this->assertSame('TERVERIFIKASI', $menu['R1 Protein']['label_data']);
        $this->assertSame('TERVERIFIKASI', $menu['R1 Protein']['status_verifikasi_data']);
        $this->assertArrayHasKey('cara_memasak', $menu['R1 Protein']);
        $this->assertArrayHasKey('bahans', $menu['R1 Protein']);
        $this->assertSame('Bahan A', $menu['R1 Protein']['bahans'][0]['nama_bahan']);
        $this->assertArrayHasKey('takaran_satuan', $menu['R1 Protein']['bahans'][0]);
    }

    public function test_idor_rekomendasi_403(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $lain = User::factory()->create();

        $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => '2026-01-15',
        ], $this->as($lain))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_data_lengkap_hanya_dipakai_dalam_mode_demo(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $h = $this->as($ortu);
        $payload = ['tanggal' => '2026-01-15'];

        config(['nutrition.rekomendasi.demo_allow_data_lengkap' => true]);

        $demo = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", $payload, $h)
            ->assertOk()
            ->assertJsonPath('data.mode_demo', true)
            ->assertJsonPath('data.label_data', 'DEMO_BELUM_TERVERIFIKASI');

        $demoMenu = collect($demo->json('data.menu'))->keyBy('nama_resep');
        $this->assertTrue($demoMenu->has('R4 Data Lengkap'));
        $this->assertSame('DEMO_BELUM_TERVERIFIKASI', $demoMenu['R4 Data Lengkap']['label_data']);

        config(['nutrition.rekomendasi.demo_allow_data_lengkap' => false]);

        $normal = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", $payload, $h)
            ->assertOk()
            ->assertJsonMissingPath('data.mode_demo');

        $this->assertSame(2, $normal->json('data.jumlah_rekomendasi'));
        $this->assertFalse(collect($normal->json('data.menu'))->keyBy('nama_resep')->has('R4 Data Lengkap'));
    }

    public function test_data_lengkap_tetap_ditolak_di_production(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $h = $this->as($ortu);
        $environment = $this->app['env'];

        config(['nutrition.rekomendasi.demo_allow_data_lengkap' => true]);
        $this->app['env'] = 'production';

        try {
            $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
                'tanggal' => '2026-01-15',
            ], $h)->assertOk()->assertJsonMissingPath('data.mode_demo');

            $menu = collect($res->json('data.menu'))->keyBy('nama_resep');
            $this->assertSame(2, $res->json('data.jumlah_rekomendasi'));
            $this->assertFalse($menu->has('R4 Data Lengkap'));
        } finally {
            $this->app['env'] = $environment;
        }
    }

    public function test_resep_tanpa_empat_makro_tidak_direkomendasikan_walau_data_lengkap_atau_demo(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'a' => $a] = $this->fixtures();
        $h = $this->as($ortu);

        foreach ([Resep::VERIFIKASI_DATA_LENGKAP, Resep::VERIFIKASI_TERVERIFIKASI] as $status) {
            $resep = $this->resep("Tanpa Makro {$status}", 'tanpa makro', [[$a, 100]], $status);
            $resep->update([
                'energi_per_porsi' => null,
                'karbohidrat_per_porsi' => null,
                'protein_per_porsi' => null,
                'lemak_per_porsi' => null,
            ]);
        }

        config(['nutrition.rekomendasi.demo_allow_data_lengkap' => true]);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => '2026-01-15',
            'top_k' => 5,
        ], $h)->assertOk();

        $menu = collect($res->json('data.menu'))->keyBy('nama_resep');
        $this->assertFalse($menu->has('Tanpa Makro DATA_LENGKAP'));
        $this->assertFalse($menu->has('Tanpa Makro TERVERIFIKASI'));
        $this->assertTrue($menu->has('R4 Data Lengkap'));

        config(['nutrition.rekomendasi.demo_allow_data_lengkap' => false]);

        $normal = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => '2026-01-15',
            'top_k' => 5,
        ], $h)->assertOk();

        $nama = array_column($normal->json('data.menu'), 'nama_resep');
        $this->assertNotContains('Tanpa Makro DATA_LENGKAP', $nama);
        $this->assertNotContains('Tanpa Makro TERVERIFIKASI', $nama);
    }

    public function test_resep_tanpa_bahan_satuan_atau_cara_memasak_tidak_direkomendasikan(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'a' => $a] = $this->fixtures();
        $h = $this->as($ortu);

        $this->resep('Tanpa Bahan', 'tinggi protein', []);
        $tanpaSatuan = $this->resep('Tanpa Satuan', 'tinggi protein', [[$a, 100]]);
        $tanpaSatuan->bahans()->firstOrFail()->update(['takaran_satuan' => '']);
        $this->resep('Tanpa Cara Masak', 'tinggi protein', [[$a, 100]], Resep::VERIFIKASI_TERVERIFIKASI, '');

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => '2026-01-15',
        ], $h)->assertOk();

        $menu = collect($res->json('data.menu'))->keyBy('nama_resep');
        $this->assertFalse($menu->has('Tanpa Bahan'));
        $this->assertFalse($menu->has('Tanpa Satuan'));
        $this->assertFalse($menu->has('Tanpa Cara Masak'));
    }

    public function test_pesan_kosong_rekomendasi_menyebut_resep_terverifikasi(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'r1' => $r1, 'r2' => $r2] = $this->fixtures();
        $r1->update(['status_verifikasi_data' => Resep::VERIFIKASI_PERLU_VERIFIKASI]);
        $r2->update(['status_verifikasi_data' => Resep::VERIFIKASI_PERLU_VERIFIKASI]);

        $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => '2026-01-15',
        ], $this->as($ortu))
            ->assertOk()
            ->assertJsonPath('data.jumlah_rekomendasi', 0)
            ->assertJsonPath('data.saran', 'Belum ada resep terverifikasi yang cocok untuk kriteria ini.');
    }

    public function test_gizi_null_tetap_direkomendasikan_dan_null_di_respons(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'r1' => $r1] = $this->fixtures();
        $r1->update([
            'zinc_per_porsi' => null,
            'vitamin_c_per_porsi' => null,
        ]);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => '2026-01-15',
            'top_k' => 5,
        ], $this->as($ortu))->assertOk();

        $item = collect($res->json('data.menu'))->firstWhere('nama_resep', 'R1 Protein');

        $this->assertNotNull($item);
        $this->assertNull($item['gizi_per_porsi']['zinc']);
        $this->assertNull($item['gizi_per_porsi']['vitamin_c']);
        $this->assertEqualsWithDelta(12.0, (float) $item['gizi_per_porsi']['protein'], 0.01);

        $this->getJson("/api/v1/resep/{$r1->id}", $this->as($ortu))
            ->assertOk()
            ->assertJsonPath('data.gizi_per_porsi.zinc', null)
            ->assertJsonPath('data.gizi_per_porsi.vitamin_c', null);
    }

    public function test_detail_resep_tanpa_biaya_dan_404(): void
    {
        ['ortu' => $ortu, 'r1' => $r1] = $this->fixtures();

        $res = $this->getJson("/api/v1/resep/{$r1->id}", $this->as($ortu))
            ->assertOk()
            ->assertJsonPath('data.nama_resep', 'R1 Protein')
            ->assertJsonPath('data.jumlah_porsi_hasil', 1)
            ->assertJsonMissingPath('data.estimasi_biaya')
            ->assertJsonMissingPath('data.biaya_lengkap')
            ->assertJsonMissingPath('data.porsi_saji');

        $this->assertCount(2, $res->json('data.bahans'));
        $this->assertArrayNotHasKey('takaran_gram', $res->json('data.bahans.0'));
        $this->assertEqualsWithDelta(12.0, (float) $res->json('data.gizi_per_porsi.protein'), 0.01);
        $this->assertArrayHasKey('energi', $res->json('data.gizi_per_porsi'));

        $this->getJson('/api/v1/resep/00000000-0000-0000-0000-000000000000', $this->as($ortu))
            ->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
    }
}
