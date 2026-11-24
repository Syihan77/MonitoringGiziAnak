<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\BahanMakanan;
use App\Models\PantanganAnak;
use App\Models\RekomendasiLog;
use App\Models\Resep;
use App\Models\User;
use App\Services\NutritionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

class RekomendasiMl2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('services.ml.enabled', true);
        // Kunci alamat ML agar tes tidak bergantung pada .env mesin pengembang.
        config()->set('services.ml.ml1.base_url', 'http://127.0.0.1:8000');
        config()->set('services.ml.ml2.base_url', 'http://127.0.0.1:8000');
    }

    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    private function bahan(string $nama): BahanMakanan
    {
        return BahanMakanan::create([
            'nama_bahan' => $nama,
            'kelompok_gizi' => 'Uji',
            'protein_per100g' => 5,
            'zat_besi_per100g' => 2,
        ]);
    }

    private function resep(
        string $nama,
        string $idResep,
        BahanMakanan $bahan,
        string $status = Resep::VERIFIKASI_TERVERIFIKASI,
    ): Resep {
        $resep = Resep::create([
            'nama_resep' => $nama,
            'id_resep' => $idResep,
            'cara_memasak' => 'Masak.',
            'kategori_gizi' => 'Uji',
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

        $resep->bahans()->create([
            'bahan_id' => $bahan->id,
            'takaran_jumlah' => 100,
            'takaran_satuan' => 'g',
            'takaran_gram_ekuivalen' => 100,
        ]);

        return $resep;
    }

    /**
     * @return array{ortu: User, anak: Anak, a: BahanMakanan, b: BahanMakanan, r1: Resep, r2: Resep, r3: Resep}
     */
    private function fixtures(): array
    {
        $ortu = User::factory()->create();
        $anak = Anak::create([
            'user_id' => $ortu->id,
            'nama' => 'Budi',
            'tanggal_lahir' => '2024-01-15',
            'jenis_kelamin' => 'L',
        ]);

        $a = $this->bahan('Bahan A');
        $b = $this->bahan('Bahan B');

        $r1 = $this->resep('R1 Protein', 'R1', $a);
        $r2 = $this->resep('R2 Besi', 'R2', $a);
        $r3 = $this->resep('R3 Energi', 'R3', $a);

        return compact('ortu', 'anak', 'a', 'b', 'r1', 'r2', 'r3');
    }

    private function mockAnalisis(?array $ringkasan = null): void
    {
        $this->mock(NutritionService::class, function (MockInterface $mock) use ($ringkasan): void {
            $mock->shouldReceive('analisis')->andReturn([
                'status_kondisi' => null,
                'status_analisis' => NutritionService::STATUS_DIANALISIS,
                'ringkasan' => $ringkasan ?? [
                    ['zat_gizi' => 'protein', 'status' => 'kurang'],
                    ['zat_gizi' => 'seng', 'status' => 'kurang'],
                ],
            ]);
        });
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, mixed>
     */
    private function responsMl2(array $ids): array
    {
        $hasil = [];

        foreach ($ids as $index => $id) {
            $hasil[] = [
                'rank' => $index + 1,
                'id_resep' => $id,
                'nama_menu' => 'Menu '.$id,
                'score' => round(0.9 - $index * 0.1, 4),
            ];
        }

        return [
            'meta' => [
                'versi_modul' => '1.0.0',
                'versi_model' => '1.1.0-tkpi',
                'metode' => 'baseline',
                'mode_bahan' => 'nonaktif',
                'kebijakan_nilai_hilang' => 'penalti',
                'top_n' => 50,
                'peringatan' => [],
            ],
            'hasil' => $hasil,
        ];
    }

    private function minta(User $user, Anak $anak, array $payload = []): TestResponse
    {
        return $this->postJson(
            "/api/v1/anak/{$anak->id}/rekomendasi-menu",
            $payload + ['tanggal' => '2026-01-15'],
            $this->as($user),
        );
    }

    private function assertFallback(TestResponse $res, string $alasan): void
    {
        $res->assertOk()
            ->assertJsonPath('data.metode_ranking', RekomendasiLog::METODE_BASELINE_ATURAN)
            ->assertJsonPath('data.ml2.tersedia', false)
            ->assertJsonPath('data.ml2.alasan_tidak_tersedia', $alasan)
            ->assertJsonPath('data.ml2.jumlah_dipakai', 0);

        foreach ($res->json('data.menu') as $item) {
            $this->assertSame('LOKAL', $item['sumber_ranking']);
        }
    }

    public function test_status_gizi_tidak_tersedia_tidak_menjadi_defisiensi(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        config()->set('services.ml.enabled', false);
        $this->mock(NutritionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('analisis')->andReturn([
                'status_kondisi' => null,
                'status_analisis' => NutritionService::STATUS_DIANALISIS,
                'ringkasan' => [
                    ['zat_gizi' => 'protein', 'status' => 'kurang'],
                    ['zat_gizi' => 'seng', 'status' => 'tidak_tersedia'],
                ],
            ]);
        });

        $res = $this->minta($ortu, $anak, ['top_k' => 2])->assertOk();

        $this->assertSame(['protein'], $res->json('data.defisiensi_terdeteksi'));
        $this->assertSame(RekomendasiLog::METODE_BASELINE_ATURAN, $res->json('data.metode_ranking'));
    }

    public function test_energi_saja_tidak_memicu_ml2_atau_defisiensi(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis([
            ['zat_gizi' => 'energi', 'status' => 'kurang'],
        ]);

        $res = $this->minta($ortu, $anak, ['top_k' => 2])->assertOk()
            ->assertJsonPath('data.metode_ranking', RekomendasiLog::METODE_BASELINE_ATURAN)
            ->assertJsonPath('data.ml2.tersedia', false)
            ->assertJsonPath('data.ml2.alasan_tidak_tersedia', 'tanpa_defisiensi');

        $this->assertSame([], $res->json('data.defisiensi_terdeteksi'));

        foreach ($res->json('data.menu') as $item) {
            $this->assertSame('LOKAL', $item['sumber_ranking']);
            $this->assertStringContainsString('tidak ada defisiensi terdeteksi', $item['alasan_peringkat']);
        }
    }

    public function test_energi_tidak_diteruskan_sebagai_prioritas_ml2(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis([
            ['zat_gizi' => 'energi', 'status' => 'kurang'],
            ['zat_gizi' => 'protein', 'status' => 'kurang'],
        ]);

        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response(
                $this->responsMl2(['R1']),
                200,
            ),
        ]);

        $res = $this->minta($ortu, $anak, ['top_k' => 1])->assertOk();

        $this->assertSame(['protein'], $res->json('data.defisiensi_terdeteksi'));

        Http::assertSent(function (Request $request): bool {
            $prioritas = $request->data()['prioritas_gizi'] ?? [];

            return array_keys($prioritas) === ['protein']
                && (float) $prioritas['protein'] === 1.0;
        });
    }

    public function test_ml2_sukses_mengurutkan_hasil_dan_mengabaikan_id_tidak_dikenal(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'a' => $a, 'b' => $b] = $this->fixtures();
        PantanganAnak::create([
            'anak_id' => $anak->id,
            'bahan_id' => $b->id,
            'alergen' => 'Udang',
            'jenis' => PantanganAnak::JENIS_PANTANGAN,
        ]);
        $this->mockAnalisis();

        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response(
                $this->responsMl2(['R3', 'R1', 'R999']),
                200,
            ),
        ]);

        $res = $this->minta($ortu, $anak, [
            'bahan_tersedia' => [$a->id],
            'top_k' => 2,
        ])->assertOk()
            ->assertJsonPath('data.metode_ranking', RekomendasiLog::METODE_MODEL_ML)
            ->assertJsonPath('data.ml2.tersedia', true)
            ->assertJsonPath('data.ml2.alasan_tidak_tersedia', null)
            ->assertJsonPath('data.ml2.status_model', 'EKSPERIMEN')
            ->assertJsonPath('data.ml2.jenis_ranking', 'PERSENTIL_CONTENT_BASED')
            ->assertJsonPath('data.ml2.status_validasi', 'BELUM_DIVALIDASI_AHLI_GIZI')
            ->assertJsonPath('data.ml2.versi_model', '1.1.0-tkpi')
            ->assertJsonPath('data.ml2.jumlah_dikembalikan', 3)
            ->assertJsonPath('data.ml2.jumlah_dipakai', 2);

        $menu = $res->json('data.menu');

        $this->assertSame(['R3 Energi', 'R1 Protein'], array_column($menu, 'nama_resep'));
        $this->assertSame(['ML2', 'ML2'], array_column($menu, 'sumber_ranking'));
        $this->assertArrayNotHasKey('cluster', $menu[0]);
        $this->assertArrayNotHasKey('cluster_ml', $menu[0]);
        $this->assertArrayNotHasKey('cluster', $res->json('data.ml2'));
        $this->assertSame([0.9, 0.8], array_column($menu, 'skor_ml2'));
        $this->assertArrayNotHasKey('R999', array_column($menu, 'nama_resep'));

        $bobotUmum = (float) config('nutrition.rekomendasi.bobot_gizi_umum');
        $bobotRelevansi = (float) config('nutrition.rekomendasi.bobot_relevansi');
        foreach ($menu as $item) {
            $this->assertEqualsWithDelta(
                round($item['skor_gizi_umum'] * $bobotUmum + $item['skor_relevansi'] * $bobotRelevansi, 3),
                $item['skor_akhir'],
                0.0001,
            );
        }

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'http://127.0.0.1:8000/ml2/rekomendasi'
                && $data['top_n'] === 50
                && $data['metode'] === 'baseline'
                && $data['kebijakan_nilai_hilang'] === 'penalti'
                && $data['mode_bahan'] === 'sebagian'
                && $data['bahan_tersedia'] === ['Bahan A']
                && $data['prioritas_gizi'] == ['protein' => 1, 'zinc' => 1]
                && in_array('Bahan B', $data['bahan_dihindari'], true)
                && in_array('udang', $data['bahan_dihindari'], true)
                && ! array_key_exists('id_resep_diizinkan', $data);
        });
    }

    public function test_resep_tanpa_makro_tidak_mengisi_ml2_termasuk_vitamin_c_top_n_50(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'r2' => $r2] = $this->fixtures();
        $r2->update([
            'energi_per_porsi' => null,
            'karbohidrat_per_porsi' => null,
            'protein_per_porsi' => null,
            'lemak_per_porsi' => null,
        ]);
        $this->mockAnalisis([
            ['zat_gizi' => 'vitamin_c', 'status' => 'kurang'],
        ]);

        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response(
                $this->responsMl2(['R2', 'R1']),
                200,
            ),
        ]);

        $res = $this->minta($ortu, $anak, ['top_k' => 2])->assertOk()
            ->assertJsonPath('data.ml2.jumlah_dikembalikan', 2)
            ->assertJsonPath('data.ml2.jumlah_dipakai', 1);

        $menu = $res->json('data.menu');
        $this->assertNotContains('R2 Besi', array_column($menu, 'nama_resep'));
        $this->assertSame('R1 Protein', $menu[0]['nama_resep']);
        $this->assertSame('ML2', $menu[0]['sumber_ranking']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $data['top_n'] === 50
                && $data['prioritas_gizi'] == ['vitamin_c' => 1];
        });
    }

    public function test_tanpa_bahan_mengirim_mode_nonaktif(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis();

        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response($this->responsMl2(['R1']), 200),
        ]);

        $this->minta($ortu, $anak, ['top_k' => 1])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $data['mode_bahan'] === 'nonaktif'
                && $data['bahan_tersedia'] === []
                && $data['bahan_dihindari'] === [];
        });
    }

    public function test_timeout_fallback_ke_ranking_lokal(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis();
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out after 5001 milliseconds',
        ));

        $this->assertFallback(
            $this->minta($ortu, $anak, ['top_k' => 3]),
            'timeout',
        );
    }

    public function test_422_fallback_ke_ranking_lokal(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis();
        Http::fake(['*' => Http::response(['detail' => 'input tidak valid'], 422)]);

        $this->assertFallback(
            $this->minta($ortu, $anak, ['top_k' => 3]),
            'http_4xx',
        );
    }

    public function test_503_fallback_ke_ranking_lokal(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis();
        Http::fake(['*' => Http::response(['status' => 'error'], 503)]);

        $this->assertFallback(
            $this->minta($ortu, $anak, ['top_k' => 3]),
            'http_5xx',
        );
    }

    public function test_hasil_kosong_fallback_ke_ranking_lokal(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis();
        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response($this->responsMl2([]), 200),
        ]);

        $this->assertFallback(
            $this->minta($ortu, $anak, ['top_k' => 3]),
            'hasil_kosong',
        );
    }

    public function test_id_ml2_tidak_dikenal_diabaikan(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis();
        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response($this->responsMl2(['R999']), 200),
        ]);

        $this->assertFallback(
            $this->minta($ortu, $anak, ['top_k' => 2]),
            'hasil_tidak_cocok',
        );
    }

    public function test_hasil_ml2_sebagian_dilengkapi_ranking_lokal(): void
    {
        ['ortu' => $ortu, 'anak' => $anak] = $this->fixtures();
        $this->mockAnalisis();
        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response(
                $this->responsMl2(['R3', 'R999']),
                200,
            ),
        ]);

        $res = $this->minta($ortu, $anak, ['top_k' => 3])->assertOk()
            ->assertJsonPath('data.metode_ranking', RekomendasiLog::METODE_MODEL_ML)
            ->assertJsonPath('data.ml2.jumlah_dikembalikan', 2)
            ->assertJsonPath('data.ml2.jumlah_dipakai', 1);

        $menu = $res->json('data.menu');

        $this->assertCount(3, $menu);
        $this->assertSame('R3 Energi', $menu[0]['nama_resep']);
        $this->assertSame('ML2', $menu[0]['sumber_ranking']);
        $this->assertSame(['LOKAL', 'LOKAL'], array_column(array_slice($menu, 1), 'sumber_ranking'));
    }

    public function test_perlu_verifikasi_tidak_pernah_lolos_walau_dikembalikan_ml2(): void
    {
        ['ortu' => $ortu, 'anak' => $anak, 'a' => $a] = $this->fixtures();
        $this->resep('R4 Perlu Verifikasi', 'R4', $a, Resep::VERIFIKASI_PERLU_VERIFIKASI);
        $this->mockAnalisis();
        Http::fake([
            'http://127.0.0.1:8000/ml2/rekomendasi' => Http::response($this->responsMl2(['R4']), 200),
        ]);

        $res = $this->minta($ortu, $anak, ['top_k' => 3])->assertOk();
        $this->assertFallback($res, 'hasil_tidak_cocok');

        $nama = array_column($res->json('data.menu'), 'nama_resep');
        $this->assertNotContains('R4 Perlu Verifikasi', $nama);
    }
}
