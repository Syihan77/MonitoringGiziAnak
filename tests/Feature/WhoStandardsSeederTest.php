<?php

namespace Tests\Feature;

use App\Models\WhoStandard;
use Database\Seeders\WhoStandardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhoStandardsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_md_menghasilkan_729_baris_tanpa_imt(): void
    {
        $rows = WhoStandardsSeeder::parseFile(WhoStandardsSeeder::sourcePath());

        $this->assertCount(729, $rows);
        $this->assertSame([], array_diff(
            array_unique(array_column($rows, 'indikator')), ['bb_u', 'tb_u', 'bb_tb']
        ));
    }

    public function test_parse_melewati_baris_korup_69_5(): void
    {
        $rows = WhoStandardsSeeder::parseFile(WhoStandardsSeeder::sourcePath());

        $korup = array_filter($rows, fn ($r) => $r['indikator'] === 'bb_tb'
            && $r['jenis_kelamin'] === 'P' && $r['varian'] === 'tb'
            && (float) $r['panjang_cm'] === 69.5);

        $this->assertCount(0, $korup);
    }

    public function test_seed_menulis_anchor_permenkes(): void
    {
        $this->seed(WhoStandardsSeeder::class);

        $this->assertSame(729, WhoStandard::count());

        $bbU = WhoStandard::where('indikator', 'bb_u')->where('jenis_kelamin', 'L')->where('umur_bulan', 24)->first();
        $this->assertNotNull($bbU);
        $this->assertEqualsWithDelta(12.2, (float) $bbU->median, 0.001);

        // Cakupan umur lengkap 0-60 utk bb_u kedua jenis kelamin.
        foreach (['L', 'P'] as $jk) {
            $this->assertSame(61, WhoStandard::where('indikator', 'bb_u')->where('jenis_kelamin', $jk)->count());
        }
    }
}
