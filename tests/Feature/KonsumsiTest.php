<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\BahanMakanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KonsumsiTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    private function bahanProtein(): BahanMakanan
    {
        return BahanMakanan::create([
            'nama_bahan' => 'Telur Ayam',
            'kelompok_gizi' => 'Protein Hewani',
            'energi_per100g' => 150,
            'karbohidrat_per100g' => 1,
            'protein_per100g' => 10,
            'lemak_per100g' => 11,
            'zat_besi_per100g' => 2,
            'zinc_per100g' => 1,
        ]);
    }

    private function anak24(User $owner): Anak
    {
        return Anak::create([
            'user_id' => $owner->id, 'nama' => 'Budi',
            'tanggal_lahir' => '2024-01-15', 'jenis_kelamin' => 'L',
        ]);
    }

    public function test_catat_multi_item_201_dan_satuan_kg_dikonversi(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();

        $res = $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'items' => [
                ['bahan_id' => $bahan->id, 'porsi_gram' => 100],
                ['bahan_id' => $bahan->id, 'porsi_gram' => 0.5, 'satuan' => 'kg'],
            ],
        ], $this->as($ortu));

        $res->assertCreated()
            ->assertJsonPath('data.jumlah_item', 2);

        $this->assertDatabaseHas('konsumsis', ['anak_id' => $anak->id, 'porsi_gram' => 100]);
        $this->assertDatabaseHas('konsumsis', ['anak_id' => $anak->id, 'porsi_gram' => 500]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'konsumsi_create']);
    }

    public function test_bahan_tak_dikenal_400(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'items' => [['bahan_id' => '00000000-0000-0000-0000-000000000000', 'porsi_gram' => 100]],
        ], $this->as($ortu))
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_kader_tulis_ditolak_baca_boleh(): void
    {
        $ortu = User::factory()->create(['wilayah_posyandu' => 'Posyandu Melati']);
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();
        $kader = User::factory()->kader()->create(['wilayah_posyandu' => 'Posyandu Melati']);

        $payload = [
            'tanggal_konsumsi' => '2026-01-15',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ];

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", $payload, $this->as($kader))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", $payload, $this->as($ortu))->assertCreated();

        $this->getJson("/api/v1/anak/{$anak->id}/konsumsi?tanggal=2026-01-15", $this->as($kader))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_ortu_lain_idor_403(): void
    {
        $ortuA = User::factory()->create();
        $ortuB = User::factory()->create();
        $anak = $this->anak24($ortuA);
        $bahan = $this->bahanProtein();

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $this->as($ortuB))
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    // UT-06: protein 10g dari AKG 20g (1-3th) = 50% -> sangat_kurang.
    public function test_analisis_protein_rendah_sangat_kurang(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $this->as($ortu))->assertCreated();

        $res = $this->getJson("/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-01-15", $this->as($ortu))
            ->assertOk()->assertJsonPath('success', true);

        $protein = collect($res->json('data.ringkasan'))->firstWhere('zat_gizi', 'protein');
        $this->assertEqualsWithDelta(50.0, (float) $protein['persentase_akg'], 0.01);
        $this->assertSame('sangat_kurang', $protein['status']);
        $this->assertTrue($res->json('data.rekomendasi_tersedia'));
        $this->assertSame('1–3 tahun', $res->json('data.kelompok_umur'));
    }

    public function test_analisis_batas_kurang_75_persen(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 150]],
        ], $this->as($ortu))->assertCreated();

        $res = $this->getJson("/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-01-15", $this->as($ortu))->assertOk();
        $protein = collect($res->json('data.ringkasan'))->firstWhere('zat_gizi', 'protein');

        $this->assertEqualsWithDelta(75.0, (float) $protein['persentase_akg'], 0.01);
        $this->assertSame('kurang', $protein['status']);
    }

    public function test_analisis_tanpa_data_tidak_dapat_dianalisis(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);

        $this->getJson("/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-01-15", $this->as($ortu))
            ->assertOk()
            ->assertJsonPath('data.analisis_status', 'tidak_dapat_dianalisis')
            ->assertJsonPath('data.rekomendasi_tersedia', false)
            ->assertJsonCount(0, 'data.ringkasan');
    }

    public function test_agregat_bulanan_rata_dan_kosong(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();
        $h = $this->as($ortu);

        // Bulan kosong -> belum_tercatat.
        $this->getJson("/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=2026-01", $h)
            ->assertOk()
            ->assertJsonPath('data.status', 'belum_tercatat')
            ->assertJsonPath('data.hari_tercatat', 0);

        // Hari 1: protein 10g/20g = 50%. Hari 2: 20g/20g = 100%. Rata protein 75%.
        foreach ([['2026-01-10', 100], ['2026-01-20', 200]] as [$tgl, $gram]) {
            $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
                'tanggal_konsumsi' => $tgl,
                'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => $gram]],
            ], $h)->assertCreated();
        }

        $res = $this->getJson("/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=2026-01", $h)->assertOk();

        $res->assertJsonPath('data.hari_tercatat', 2);
        $this->assertEqualsWithDelta(75.0, (float) $res->json('data.rata_per_zat.protein'), 0.01);
        // Karbohidrat terendah (bahan uji hampir tanpa KH).
        $this->assertSame('karbohidrat', $res->json('data.zat_terendah'));

        // Format bulan salah -> 400.
        $this->getJson("/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=Januari", $h)
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_agregat_bulanan_cap_100_persen(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $h = $this->as($ortu);

        // Bahan super-seng: 300mg/100g x 100g = 10000% AKG (3mg) -> di-cap 100%.
        $bahan = \App\Models\BahanMakanan::create([
            'nama_bahan' => 'Super Seng', 'kelompok_gizi' => 'Uji', 'zinc_per100g' => 300,
        ]);

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-02-10',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $h)->assertCreated();

        $res = $this->getJson("/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=2026-02", $h)->assertOk();

        $this->assertEqualsWithDelta(100.0, (float) $res->json('data.rata_per_zat.seng'), 0.01);
        $this->assertLessThanOrEqual(100.0, (float) $res->json('data.rata_persen_akg'));
        // Tanpa cap, rata akan meledak (>1000%). Status ikut zat lain yang nol.
        $this->assertSame('TIDAK TERPENUHI', $res->json('data.status'));
    }

    public function test_riwayat_rentang_tanggal(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();
        $h = $this->as($ortu);

        foreach (['2026-01-13', '2026-01-15'] as $tgl) {
            $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
                'tanggal_konsumsi' => $tgl,
                'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
            ], $h)->assertCreated();
        }

        $res = $this->getJson(
            "/api/v1/anak/{$anak->id}/konsumsi?dari_tanggal=2026-01-14&sampai_tanggal=2026-01-31", $h
        )->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('2026-01-15', $res->json('data.0.tanggal_konsumsi'));

        // tanggal + rentang bersamaan ditolak.
        $this->getJson(
            "/api/v1/anak/{$anak->id}/konsumsi?tanggal=2026-01-15&dari_tanggal=2026-01-01&sampai_tanggal=2026-01-31", $h
        )->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }
}
