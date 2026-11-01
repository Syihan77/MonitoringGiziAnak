<?php

namespace Tests\Feature;

use App\Models\Resep;
use Database\Seeders\BahanSeeder;
use Database\Seeders\ResepSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResepSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dataset_seed_lengkap_dan_idempoten(): void
    {
        $this->seed(BahanSeeder::class);
        $this->seed(ResepSeeder::class);

        $this->assertSame(93, Resep::count());
        $this->assertSame(76, Resep::where('status_verifikasi_data', 'DATA_LENGKAP')->count());
        $this->assertSame(17, Resep::where('status_verifikasi_data', 'PERLU_VERIFIKASI')->count());
        $this->assertSame(0, Resep::doesntHave('bahans')->count());

        $r001 = Resep::where('id_resep', 'R001')->firstOrFail();
        $this->assertSame('DATA_LENGKAP', $r001->status_verifikasi_data);
        $this->assertNull($r001->porsi_saji);
        $this->assertContains('telur', $r001->alergen);

        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R057')->value('status_verifikasi_data'));
        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R061')->value('status_verifikasi_data'));
        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R079')->value('status_verifikasi_data'));
        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R093')->value('status_verifikasi_data'));
        $this->assertSame('DATA_LENGKAP', Resep::where('id_resep', 'R067')->value('status_verifikasi_data'));
        $this->assertSame('DATA_LENGKAP', Resep::where('id_resep', 'R091')->value('status_verifikasi_data'));

        $r032 = Resep::where('id_resep', 'R032')->firstOrFail();
        $tahu = $r032->bahans()
            ->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Tahu putih%'))
            ->firstOrFail();
        $this->assertEqualsWithDelta(120.0, (float) $tahu->takaran_gram, 0.01);

        $r057 = Resep::where('id_resep', 'R057')->firstOrFail();
        $agar = $r057->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Agar%'))->firstOrFail();
        $this->assertNull($agar->takaran_gram);

        $jumlahBahan = $r001->bahans()->count();

        $this->seed(ResepSeeder::class);

        $this->assertSame(93, Resep::count());
        $this->assertSame($jumlahBahan, $r001->fresh()->bahans()->count());
    }
}
