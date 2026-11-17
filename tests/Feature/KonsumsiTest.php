<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\BahanMakanan;
use App\Models\User;
use App\Services\NutritionService;
use App\Support\BahanResolver;
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

    private function kondisi(User $user, Anak $anak, string $tanggal, string $status = 'TIDAK_SAKIT'): void
    {
        $this->postJson("/api/v1/anak/{$anak->id}/kondisi-harian", [
            'tanggal' => $tanggal,
            'status_kondisi' => $status,
        ], $this->as($user))->assertSuccessful();
    }

    public function test_catat_multi_item_201_dan_satuan_kg_dikonversi(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();
        $this->kondisi($ortu, $anak, '2026-01-15');

        $res = $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'metode_input' => 'MANUAL',
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
            'metode_input' => 'MANUAL',
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
        $this->kondisi($ortu, $anak, '2026-01-15');

        $payload = [
            'tanggal_konsumsi' => '2026-01-15',
            'metode_input' => 'MANUAL',
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
            'metode_input' => 'MANUAL',
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
        $this->kondisi($ortu, $anak, '2026-01-15');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'metode_input' => 'MANUAL',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $this->as($ortu))->assertCreated();

        $res = $this->getJson("/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-01-15", $this->as($ortu))
            ->assertOk()->assertJsonPath('success', true);

        $protein = collect($res->json('data.ringkasan'))->firstWhere('zat_gizi', 'protein');
        $this->assertEqualsWithDelta(50.0, (float) $protein['persentase_akg'], 0.01);
        $this->assertSame('sangat_kurang', $protein['status']);
        $this->assertTrue($res->json('data.rekomendasi_tersedia'));
        $this->assertSame('1–3 tahun', $res->json('data.kelompok_umur'));
        $this->assertSame(1, $res->json('data.jumlah_konsumsi'));
        $this->assertSame(1, $res->json('data.jumlah_konsumsi_dianalisis'));
    }

    public function test_analisis_batas_kurang_75_persen(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();
        $this->kondisi($ortu, $anak, '2026-01-15');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'metode_input' => 'MANUAL',
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
            ->assertJsonPath('data.jumlah_konsumsi', 0)
            ->assertJsonPath('data.jumlah_konsumsi_dianalisis', 0)
            ->assertJsonCount(0, 'data.ringkasan');
    }

    public function test_analisis_anak_sakit_tetap_melaporkan_jumlah_konsumsi(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();
        $this->kondisi($ortu, $anak, '2026-01-15', 'SAKIT');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'metode_input' => 'MANUAL',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $this->as($ortu))->assertCreated();

        $this->getJson("/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-01-15", $this->as($ortu))
            ->assertOk()
            ->assertJsonPath('data.jumlah_konsumsi', 1)
            ->assertJsonPath('data.jumlah_konsumsi_dianalisis', 0)
            ->assertJsonPath('data.status_analisis', NutritionService::STATUS_ANAK_SAKIT)
            ->assertJsonPath('data.analisis_status', 'tidak_dapat_dianalisis')
            ->assertJsonPath('data.penanda', NutritionService::PENANDA_ANAK_SAKIT);
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
            $this->kondisi($ortu, $anak, $tgl);
            $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
                'tanggal_konsumsi' => $tgl,
                'metode_input' => 'MANUAL',
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
        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Super Seng', 'kelompok_gizi' => 'Uji', 'zinc_per100g' => 300,
        ]);
        $this->kondisi($ortu, $anak, '2026-02-10');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-02-10',
            'metode_input' => 'MANUAL',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $h)->assertCreated();

        $res = $this->getJson("/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=2026-02", $h)->assertOk();

        $this->assertEqualsWithDelta(100.0, (float) $res->json('data.rata_per_zat.seng'), 0.01);
        $this->assertLessThanOrEqual(100.0, (float) $res->json('data.rata_persen_akg'));
        // Tanpa cap, rata akan meledak (>1000%). Zat lain NULL = tidak tersedia,
        // sehingga tidak ikut dirata-rata sebagai nol, dan status bulanan tidak
        // boleh diklaim TERPENUHI penuh.
        $this->assertNull($res->json('data.rata_per_zat.energi'));
        $this->assertSame('DATA_TIDAK_LENGKAP', $res->json('data.status'));
    }

    public function test_analisis_zat_null_tidak_dianggap_nol(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Bahan Zinc Null',
            'kelompok_gizi' => 'Uji',
            'energi_per100g' => 150,
            'karbohidrat_per100g' => 1,
            'protein_per100g' => 10,
            'lemak_per100g' => 11,
            'zat_besi_per100g' => 2,
            'zinc_per100g' => null,
        ]);
        $this->kondisi($ortu, $anak, '2026-01-15');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'metode_input' => 'MANUAL',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $this->as($ortu))->assertCreated();

        $res = $this->getJson(
            "/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-01-15",
            $this->as($ortu)
        )->assertOk();

        $zinc = collect($res->json('data.ringkasan'))->firstWhere('zat_gizi', 'seng');
        $protein = collect($res->json('data.ringkasan'))->firstWhere('zat_gizi', 'protein');

        $this->assertNull($zinc['total']);
        $this->assertNull($zinc['persentase_akg']);
        $this->assertSame('tidak_tersedia', $zinc['status']);
        $this->assertSame(50.0, (float) $protein['persentase_akg']);
        $this->assertTrue($res->json('data.rekomendasi_tersedia'));
    }

    public function test_agregat_bulanan_zat_null_tidak_dianggap_nol(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Bahan Zinc Null Bulanan',
            'kelompok_gizi' => 'Uji',
            'energi_per100g' => 150,
            'karbohidrat_per100g' => 1,
            'protein_per100g' => 10,
            'lemak_per100g' => 11,
            'zat_besi_per100g' => 2,
            'zinc_per100g' => null,
        ]);
        $this->kondisi($ortu, $anak, '2026-01-15');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-01-15',
            'metode_input' => 'MANUAL',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $this->as($ortu))->assertCreated();

        $res = $this->getJson(
            "/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=2026-01",
            $this->as($ortu)
        )->assertOk();

        $this->assertNull($res->json('data.rata_per_zat.seng'));
        $this->assertNotNull($res->json('data.rata_per_zat.protein'));
        $this->assertNotNull($res->json('data.rata_persen_akg'));
        $this->assertSame('DATA_TIDAK_LENGKAP', $res->json('data.status'));
    }

    public function test_agregat_bulanan_zat_null_di_salah_satu_hari_menandai_tidak_lengkap(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $lengkap = $this->bahanProtein();
        $zincNull = BahanMakanan::create([
            'nama_bahan' => 'Bahan Zinc Null Hari Kedua',
            'kelompok_gizi' => 'Uji',
            'energi_per100g' => 150,
            'karbohidrat_per100g' => 1,
            'protein_per100g' => 10,
            'lemak_per100g' => 11,
            'zat_besi_per100g' => 2,
            'zinc_per100g' => null,
        ]);
        $h = $this->as($ortu);

        foreach ([[$lengkap, '2026-04-10'], [$zincNull, '2026-04-11']] as [$bahan, $tgl]) {
            $this->kondisi($ortu, $anak, $tgl);
            $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
                'tanggal_konsumsi' => $tgl,
                'metode_input' => 'MANUAL',
                'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
            ], $h)->assertCreated();
        }

        $res = $this->getJson("/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=2026-04", $h)->assertOk();

        $this->assertNotNull($res->json('data.rata_per_zat.seng'));
        $this->assertSame('DATA_TIDAK_LENGKAP', $res->json('data.status'));
    }

    public function test_agregat_bulanan_data_lengkap_bisa_terpenuhi(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Bahan Lengkap AKG',
            'kelompok_gizi' => 'Uji',
            'energi_per100g' => 1350,
            'karbohidrat_per100g' => 215,
            'protein_per100g' => 20,
            'lemak_per100g' => 45,
            'zat_besi_per100g' => 7,
            'zinc_per100g' => 3,
        ]);
        $this->kondisi($ortu, $anak, '2026-03-10');

        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-03-10',
            'metode_input' => 'MANUAL',
            'items' => [['bahan_id' => $bahan->id, 'porsi_gram' => 100]],
        ], $this->as($ortu))->assertCreated();

        $res = $this->getJson(
            "/api/v1/anak/{$anak->id}/gizi-bulanan?bulan=2026-03",
            $this->as($ortu)
        )->assertOk();

        $this->assertEqualsWithDelta(100.0, (float) $res->json('data.rata_persen_akg'), 0.01);
        $this->assertSame('TERPENUHI', $res->json('data.status'));
    }

    public function test_campuran_bahan_lengkap_dan_placeholder_null_lalu_dipulihkan_backfill(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $lengkap = $this->bahanProtein();
        $placeholder = BahanMakanan::create([
            'nama_bahan' => 'Ayam fillet',
            'kelompok_gizi' => 'Dataset',
            'sumber_rujukan' => BahanResolver::LABEL_PLACEHOLDER,
        ]);
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
        $h = $this->as($ortu);

        $this->kondisi($ortu, $anak, '2026-05-10');
        $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
            'tanggal_konsumsi' => '2026-05-10',
            'metode_input' => 'MANUAL',
            'items' => [
                ['bahan_id' => $lengkap->id, 'porsi_gram' => 100],
                ['bahan_id' => $placeholder->id, 'porsi_gram' => 100],
            ],
        ], $h)->assertCreated();

        $sebelum = $this->getJson("/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-05-10", $h)
            ->assertOk()
            ->assertJsonPath('data.jumlah_konsumsi', 2)
            ->assertJsonPath('data.jumlah_konsumsi_dianalisis', 2);

        $proteinSebelum = collect($sebelum->json('data.ringkasan'))->firstWhere('zat_gizi', 'protein');
        $this->assertSame('tidak_tersedia', $proteinSebelum['status']);

        $this->artisan('bahan:backfill-gizi', ['--apply' => true])->assertSuccessful();

        $this->assertNotNull($placeholder->fresh()->protein_per100g);

        $sesudah = $this->getJson("/api/v1/anak/{$anak->id}/analisis-gizi?tanggal=2026-05-10", $h)
            ->assertOk()
            ->assertJsonPath('data.jumlah_konsumsi', 2);

        foreach ($sesudah->json('data.ringkasan') as $zat) {
            $this->assertNotNull($zat['total'], $zat['zat_gizi']);
            $this->assertNotNull($zat['persentase_akg'], $zat['zat_gizi']);
        }
    }

    public function test_riwayat_rentang_tanggal(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak24($ortu);
        $bahan = $this->bahanProtein();
        $h = $this->as($ortu);

        foreach (['2026-01-13', '2026-01-15'] as $tgl) {
            $this->kondisi($ortu, $anak, $tgl);
            $this->postJson("/api/v1/anak/{$anak->id}/konsumsi", [
                'tanggal_konsumsi' => $tgl,
                'metode_input' => 'MANUAL',
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
