<?php

namespace Tests\Feature;

use App\Models\BahanMakanan;
use Database\Seeders\BahanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BahanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_tkpi_1146_baris(): void
    {
        $rows = BahanSeeder::parseFile(BahanSeeder::sourcePath());

        $this->assertCount(1146, $rows);
    }

    public function test_seed_anchor_ar001(): void
    {
        $this->seed(BahanSeeder::class);

        $this->assertSame(1146, BahanMakanan::count());

        $beras = BahanMakanan::where('sumber_rujukan', 'TKPI 2020 AR001')->first();
        $this->assertNotNull($beras);
        $this->assertSame('Beras giling, mentah', $beras->nama_bahan);
        $this->assertEqualsWithDelta(8.4, (float) $beras->protein_per100g, 0.001);
        $this->assertEqualsWithDelta(1.8, (float) $beras->zat_besi_per100g, 0.001);
        $this->assertEqualsWithDelta(0.5, (float) $beras->zinc_per100g, 0.001);
    }
}
