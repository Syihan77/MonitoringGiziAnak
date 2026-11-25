<?php

namespace Tests\Feature;

use App\Models\Resep;
use App\Models\ResepBahan;
use App\Support\DatasetGiziTkpi;
use Database\Seeders\BahanSeeder;
use Database\Seeders\ResepSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuatGiziTkpiTest extends TestCase
{
    use RefreshDatabase;

    private function seedResep(): void
    {
        $this->seed(BahanSeeder::class);
        $this->seed(ResepSeeder::class);
    }

    public function test_nol_mineral_dan_serat_dianggap_tidak_tersedia(): void
    {
        $row = array_fill_keys(array_keys(DatasetGiziTkpi::KOLOM), '0');
        $gizi = DatasetGiziTkpi::gizi($row);

        foreach (['zat_besi_per_porsi', 'kalsium_per_porsi', 'zinc_per_porsi', 'serat_per_porsi'] as $zat) {
            $this->assertNull($gizi[$zat], $zat);
        }

        foreach ([
            'energi_per_porsi',
            'karbohidrat_per_porsi',
            'protein_per_porsi',
            'lemak_per_porsi',
            'vitamin_c_per_porsi',
            'vitamin_a_per_porsi',
        ] as $zat) {
            $this->assertSame(0.0, $gizi[$zat], $zat);
        }
    }

    public function test_makro_kosong_diturunkan_dengan_catatan_idempoten(): void
    {
        $this->seedResep();

        Resep::where('id_resep', 'R001')->update([
            'status_verifikasi_data' => Resep::VERIFIKASI_DATA_LENGKAP,
            'catatan_resep' => DatasetGiziTkpi::CATATAN,
        ]);

        $this->artisan('resep:muat-gizi-tkpi', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run: 1 resep, 0 sel gizi, 1 catatan berubah.')
            ->expectsOutputToContain('Status diturunkan ke PERLU_VERIFIKASI: 1 resep.')
            ->assertSuccessful();

        $this->assertSame(
            Resep::VERIFIKASI_DATA_LENGKAP,
            Resep::where('id_resep', 'R001')->value('status_verifikasi_data'),
        );

        $this->artisan('resep:muat-gizi-tkpi')
            ->expectsOutputToContain('Muat gizi TKPI: 1 resep, 0 sel gizi, 1 catatan berubah.')
            ->expectsOutputToContain('Status diturunkan ke PERLU_VERIFIKASI: 1 resep.')
            ->assertSuccessful();

        $r001 = Resep::where('id_resep', 'R001')->firstOrFail();
        $this->assertSame(Resep::VERIFIKASI_PERLU_VERIFIKASI, $r001->status_verifikasi_data);
        $this->assertStringContainsString(Resep::CATATAN_MAKRO_TIDAK_LENGKAP, (string) $r001->catatan_resep);
        $this->assertSame(1, substr_count((string) $r001->catatan_resep, Resep::CATATAN_MAKRO_TIDAK_LENGKAP));

        $this->artisan('resep:muat-gizi-tkpi')
            ->expectsOutputToContain('Muat gizi TKPI: 0 resep, 0 sel gizi, 0 catatan berubah.')
            ->assertSuccessful();
    }

    public function test_dry_run_lalu_muat_idempoten_tanpa_mengubah_status_dan_bahan(): void
    {
        $this->seedResep();

        Resep::where('id_resep', 'R009')->update([
            'energi_per_porsi' => null,
            'karbohidrat_per_porsi' => null,
            'protein_per_porsi' => null,
            'lemak_per_porsi' => null,
            'zat_besi_per_porsi' => null,
            'vitamin_c_per_porsi' => null,
            'kalsium_per_porsi' => null,
            'zinc_per_porsi' => null,
            'vitamin_a_per_porsi' => null,
            'serat_per_porsi' => null,
            'status_verifikasi_data' => Resep::VERIFIKASI_PERLU_VERIFIKASI,
            'catatan_resep' => 'Catatan lama R009.',
        ]);
        Resep::where('id_resep', 'R058')->update([
            'kalsium_per_porsi' => 0,
            'zinc_per_porsi' => 0,
            'vitamin_c_per_porsi' => 0,
            'catatan_resep' => 'Mengandung santan (tinggi lemak, tidak otomatis dilarang). '
                .'Catatan data: kalsium dan zinc tidak tersedia (normalisasi basis ML: kalsium, zinc).',
        ]);

        $statusSebelum = Resep::pluck('status_verifikasi_data', 'id_resep')->all();
        $jumlahBahan = ResepBahan::count();

        $this->artisan('resep:muat-gizi-tkpi', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run: 2 resep, 13 sel gizi, 2 catatan berubah.')
            ->assertSuccessful();

        $this->assertNull(Resep::where('id_resep', 'R009')->value('energi_per_porsi'));
        $this->assertEqualsWithDelta(0.0, (float) Resep::where('id_resep', 'R058')->value('kalsium_per_porsi'), 0.001);
        $this->assertSame(
            'Catatan lama R009.',
            Resep::where('id_resep', 'R009')->value('catatan_resep'),
        );

        $this->artisan('resep:muat-gizi-tkpi')
            ->expectsOutputToContain('Muat gizi TKPI: 2 resep, 13 sel gizi, 2 catatan berubah.')
            ->assertSuccessful();

        $r009 = Resep::where('id_resep', 'R009')->firstOrFail();
        $this->assertEqualsWithDelta(160.65, (float) $r009->energi_per_porsi, 0.001);
        $this->assertEqualsWithDelta(40.5, (float) $r009->kalsium_per_porsi, 0.001);
        $this->assertSame(Resep::VERIFIKASI_PERLU_VERIFIKASI, $r009->status_verifikasi_data);
        $this->assertStringContainsString('Catatan lama R009.', (string) $r009->catatan_resep);
        $this->assertStringContainsString(DatasetGiziTkpi::CATATAN, (string) $r009->catatan_resep);

        $r058 = Resep::where('id_resep', 'R058')->firstOrFail();
        $this->assertEqualsWithDelta(79.66, (float) $r058->kalsium_per_porsi, 0.001);
        $this->assertEqualsWithDelta(1.06, (float) $r058->zinc_per_porsi, 0.001);
        $this->assertNull($r058->vitamin_c_per_porsi);
        $this->assertStringContainsString('Mengandung santan', (string) $r058->catatan_resep);
        $this->assertStringNotContainsString('Catatan data:', (string) $r058->catatan_resep);
        $this->assertStringNotContainsString('normalisasi basis ML', (string) $r058->catatan_resep);
        $this->assertSame(1, substr_count((string) $r058->catatan_resep, DatasetGiziTkpi::CATATAN));

        $statusSesudah = Resep::pluck('status_verifikasi_data', 'id_resep')->all();
        ksort($statusSebelum);
        ksort($statusSesudah);

        $this->assertSame($statusSebelum, $statusSesudah);
        $this->assertSame($jumlahBahan, ResepBahan::count());

        $this->artisan('resep:muat-gizi-tkpi')
            ->expectsOutputToContain('Muat gizi TKPI: 0 resep, 0 sel gizi, 0 catatan berubah.')
            ->assertSuccessful();
    }
}
