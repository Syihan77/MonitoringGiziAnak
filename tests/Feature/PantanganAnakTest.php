<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\BahanMakanan;
use App\Models\Resep;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PantanganAnakTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): array
    {
        $this->actingAs($user, 'sanctum');

        return [];
    }

    private function anak(User $ortu, ?string $tanggalLahir = null): Anak
    {
        return Anak::create([
            'user_id' => $ortu->id,
            'nama' => 'Budi',
            'tanggal_lahir' => $tanggalLahir ?? now()->subMonths(12)->toDateString(),
            'jenis_kelamin' => 'L',
        ]);
    }

    private function resep(string $nama, array $atribut = []): Resep
    {
        $resep = Resep::create(array_merge([
            'nama_resep' => $nama,
            'cara_memasak' => 'Masak hingga matang.',
            'kategori_gizi' => 'umum',
            'jumlah_porsi_hasil' => 1,
            'energi_per_porsi' => 200,
            'protein_per_porsi' => 10,
            'lemak_per_porsi' => 5,
            'karbohidrat_per_porsi' => 30,
            'serat_per_porsi' => 2,
            'kalsium_per_porsi' => 50,
            'zat_besi_per_porsi' => 2,
            'zinc_per_porsi' => 1,
            'vitamin_a_per_porsi' => 100,
            'vitamin_c_per_porsi' => 5,
            'usia_min_bulan' => 6,
            'usia_maks_bulan' => null,
            'status_verifikasi_data' => Resep::VERIFIKASI_TERVERIFIKASI,
            'alergen' => [],
        ], $atribut));

        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Bahan '.$resep->nama_resep,
            'kelompok_gizi' => 'Uji',
        ]);

        $resep->bahans()->create([
            'bahan_id' => $bahan->id,
            'takaran_jumlah' => 50,
            'takaran_satuan' => 'g',
            'takaran_gram_ekuivalen' => 50,
        ]);

        return $resep;
    }

    public function test_pantangan_disimpan_di_create_dan_update(): void
    {
        $ortu = User::factory()->create();

        $res = $this->postJson('/api/v1/anak', [
            'nama' => 'Budi',
            'tanggal_lahir' => now()->subMonths(12)->toDateString(),
            'jenis_kelamin' => 'L',
            'pantangan' => [
                ['alergen' => 'telur', 'jenis' => 'ALERGI'],
                ['alergen' => 'ikan', 'jenis' => 'ALERGI'],
            ],
        ], $this->as($ortu))->assertCreated();

        $anakId = $res->json('data.anak_id');
        $this->assertDatabaseHas('pantangan_anaks', ['anak_id' => $anakId, 'alergen' => 'telur']);

        $detail = $this->getJson("/api/v1/anak/{$anakId}", $this->as($ortu))
            ->assertOk()
            ->assertJsonCount(2, 'data.pantangan');

        $this->assertSame('telur', $detail->json('data.pantangan.0.alergen'));

        $this->putJson("/api/v1/anak/{$anakId}", [
            'pantangan' => [['alergen' => 'kedelai', 'jenis' => 'ALERGI']],
        ], $this->as($ortu))->assertOk();

        $this->assertDatabaseMissing('pantangan_anaks', ['anak_id' => $anakId, 'alergen' => 'telur']);
        $this->assertDatabaseHas('pantangan_anaks', ['anak_id' => $anakId, 'alergen' => 'kedelai']);

        $this->putJson("/api/v1/anak/{$anakId}", ['pantangan' => []], $this->as($ortu))->assertOk();
        $this->assertDatabaseMissing('pantangan_anaks', ['anak_id' => $anakId]);
    }

    public function test_jenis_pantangan_default_sesuai_sumber(): void
    {
        $ortu = User::factory()->create();
        $bahan = BahanMakanan::create([
            'nama_bahan' => 'Bahan Default',
            'kelompok_gizi' => 'Uji',
        ]);

        $res = $this->postJson('/api/v1/anak', [
            'nama' => 'Budi',
            'tanggal_lahir' => now()->subMonths(12)->toDateString(),
            'jenis_kelamin' => 'L',
            'pantangan' => [
                ['bahan_id' => $bahan->id],
                ['alergen' => 'telur'],
            ],
        ], $this->as($ortu))->assertCreated();

        $anakId = $res->json('data.anak_id');

        $this->assertDatabaseHas('pantangan_anaks', [
            'anak_id' => $anakId,
            'bahan_id' => $bahan->id,
            'jenis' => 'PANTANGAN',
        ]);
        $this->assertDatabaseHas('pantangan_anaks', [
            'anak_id' => $anakId,
            'alergen' => 'telur',
            'jenis' => 'ALERGI',
        ]);
    }

    public function test_database_menolak_pantangan_tanpa_sumber(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK constraint hanya dibuat untuk PostgreSQL.');
        }

        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $this->expectException(QueryException::class);

        $anak->pantangan()->create(['jenis' => 'ALERGI']);
    }

    public function test_pantangan_tanpa_bahan_atau_alergen_ditolak(): void
    {
        $ortu = User::factory()->create();

        $this->postJson('/api/v1/anak', [
            'nama' => 'Budi',
            'tanggal_lahir' => now()->subMonths(12)->toDateString(),
            'jenis_kelamin' => 'L',
            'pantangan' => [['jenis' => 'ALERGI']],
        ], $this->as($ortu))
            ->assertBadRequest()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_rekomendasi_menyaring_alergen_dan_bahan_pantangan(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak($ortu);

        $bahanPantangan = BahanMakanan::create([
            'nama_bahan' => 'Bahan Pantangan',
            'kelompok_gizi' => 'Uji',
        ]);

        $anak->pantangan()->create(['bahan_id' => $bahanPantangan->id, 'jenis' => 'PANTANGAN']);
        $anak->pantangan()->create(['alergen' => 'telur', 'jenis' => 'ALERGI']);

        $this->resep('Mengandung Telur', ['alergen' => ['telur']]);
        $this->resep('Mengandung Bahan Pantangan')->bahans()->create([
            'bahan_id' => $bahanPantangan->id,
            'takaran_jumlah' => 50,
            'takaran_satuan' => 'g',
            'takaran_gram_ekuivalen' => 50,
        ]);
        $this->resep('Alergen Tidak Diketahui', ['alergen' => null]);
        $this->resep('Aman');

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => today()->toDateString(),
        ], $this->as($ortu))->assertOk();

        $nama = collect($res->json('data.menu'))->pluck('nama_resep')->all();

        $this->assertSame(['Aman'], $nama);
        $this->assertSame('dilakukan', $res->json('data.pemeriksaan_pantangan'));
    }

    public function test_rekomendasi_menyertakan_resep_modifikasi_usia(): void
    {
        $ortu = User::factory()->create();
        $anak = $this->anak($ortu, now()->subMonths(9)->toDateString());

        $this->resep('Resep Modifikasi', [
            'usia_min_bulan' => 12,
            'usia_minimal_bulan_jika_dimodifikasi' => 8,
            'modifikasi_untuk_turunkan_usia' => 'Lumatkan tekstur dan tanpa garam.',
        ]);

        $this->resep('Resep Tanpa Instruksi', [
            'usia_min_bulan' => 12,
            'usia_minimal_bulan_jika_dimodifikasi' => 8,
            'modifikasi_untuk_turunkan_usia' => null,
        ]);

        $res = $this->postJson("/api/v1/anak/{$anak->id}/rekomendasi-menu", [
            'tanggal' => today()->toDateString(),
        ], $this->as($ortu))->assertOk();

        $this->assertSame(['Resep Modifikasi'], collect($res->json('data.menu'))->pluck('nama_resep')->all());
        $this->assertTrue($res->json('data.menu.0.perlu_modifikasi'));
        $this->assertSame('Lumatkan tekstur dan tanpa garam.', $res->json('data.menu.0.instruksi_modifikasi'));
        $this->assertSame('tidak_dilakukan', $res->json('data.pemeriksaan_pantangan'));
    }
}
