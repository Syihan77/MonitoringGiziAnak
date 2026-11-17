<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\Pertumbuhan;
use App\Models\User;
use Database\Seeders\WhoStandardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class Ml1PertumbuhanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WhoStandardsSeeder::class);
        Http::preventStrayRequests();
        config()->set('services.ml.enabled', true);
    }

    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    private function anak(User $owner, string $tanggalLahir = '2024-01-15'): Anak
    {
        return Anak::create([
            'user_id' => $owner->id,
            'nama' => 'Budi',
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => 'L',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function responsMl1(): array
    {
        return [
            'status' => 'ok',
            'versi_model' => 'ml1-rf-fitur-mentah-1.0.0',
            'status_model' => 'EKSPERIMEN - belum lolos evaluasi/validasi',
            'acuan' => [
                'aturan_versi' => 'v0-provisional',
                'kategori_id' => 1,
                'kategori' => 'At Risk',
                'zscore' => ['waz' => -1.46, 'haz' => -2.04, 'whz' => -0.51],
                'indikator_terburuk' => 'haz',
            ],
            'prediksi_ml' => [
                'kategori_id' => 1,
                'kategori' => 'At Risk',
                'probabilitas' => ['Normal' => 0.1, 'At Risk' => 0.9, 'High Risk' => 0.0],
            ],
            'ml_sama_dengan_acuan' => true,
            'peringatan' => [],
            'catatan' => 'Alat bantu pemantauan, bukan diagnosis.',
        ];
    }

    private function assertMl1Gagal(
        TestResponse $res,
        string $alasan,
        string $kategori = Pertumbuhan::KATEGORI_NORMAL_LOW_RISK,
    ): void {
        $res->assertCreated()
            ->assertJsonPath('data.ml1.tersedia', false)
            ->assertJsonPath('data.ml1.alasan_tidak_tersedia', $alasan)
            ->assertJsonPath('data.kategori_pemantauan', $kategori)
            ->assertJsonPath('data.metode_klasifikasi', Pertumbuhan::METODE_ACUAN_ATURAN);
    }

    public function test_ml1_sukses_tetapi_kategori_tetap_baseline(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/ml1/prediksi' => Http::response($this->responsMl1(), 200),
        ]);

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $res->assertCreated()
            ->assertJsonPath('data.ml1.tersedia', true)
            ->assertJsonPath('data.ml1.versi_model', 'ml1-rf-fitur-mentah-1.0.0')
            ->assertJsonPath('data.ml1.acuan.kategori', 'At Risk')
            ->assertJsonPath('data.kategori_pemantauan', Pertumbuhan::KATEGORI_NORMAL_LOW_RISK)
            ->assertJsonPath('data.metode_klasifikasi', Pertumbuhan::METODE_ACUAN_ATURAN);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($anak): bool {
            $data = $request->data();

            return $request->url() === 'http://127.0.0.1:8000/ml1/prediksi'
                && $data === [
                    'usia_bulan' => 24,
                    'jk' => 'L',
                    'bb_kg' => 12.5,
                    'tb_cm' => 85.2,
                ]
                && ! array_key_exists('nama', $data)
                && ! array_key_exists('anak_id', $data)
                && ! str_contains(json_encode($data), (string) $anak->id);
        });
    }

    public function test_ml1_timeout_tetap_201_dan_kategori_baseline(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out after 2001 milliseconds',
        ));

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'timeout');
    }

    public function test_ml1_koneksi_gagal_tetap_201_dan_kategori_baseline(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Connection timed out after 1003 milliseconds',
        ));

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'koneksi_gagal');
    }

    public function test_ml1_422_tetap_201_dan_kategori_baseline(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error'], 422)]);

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'http_4xx');
    }

    public function test_ml1_500_tetap_201_dan_kategori_baseline(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error'], 500)]);

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'http_5xx');
    }

    public function test_ml1_json_rusak_tetap_201_dan_kategori_baseline(): void
    {
        Http::fake([
            '*' => Http::response('bukan-json', 200, ['Content-Type' => 'application/json']),
        ]);

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'json_rusak');
    }

    public function test_ml1_bentuk_respons_tidak_valid_tetap_201(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'respons_tidak_valid');
    }

    public function test_ml1_tidak_dipanggil_untuk_usia_di_bawah_12_bulan(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak($ortu, '2025-07-15');

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 7.5,
            'tinggi_badan_cm' => 65,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'usia_di_luar_cakupan_ml1');
        Http::assertNothingSent();
    }

    public function test_ml1_tidak_dipanggil_untuk_usia_60_bulan(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak($ortu, '2021-01-15');

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 18,
            'tinggi_badan_cm' => 110,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'usia_di_luar_cakupan_ml1');
        Http::assertNothingSent();
    }

    public function test_ml1_tidak_dipanggil_untuk_bb_di_luar_cakupan(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 1.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'input_di_luar_cakupan_ml1', Pertumbuhan::KATEGORI_HIGH_RISK_REFERRAL);
        Http::assertNothingSent();
    }

    public function test_ml1_tidak_dipanggil_saat_ml_disabled(): void
    {
        config()->set('services.ml.enabled', false);

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/pertumbuhan", [
            'berat_badan_kg' => 12.5,
            'tinggi_badan_cm' => 85.2,
            'tanggal_ukur' => '2026-01-15',
        ], $this->as($ortu));

        $this->assertMl1Gagal($res, 'disabled');
        Http::assertNothingSent();
    }
}
