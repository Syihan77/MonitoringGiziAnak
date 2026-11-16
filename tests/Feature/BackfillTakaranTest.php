<?php

namespace Tests\Feature;

use App\Models\Resep;
use App\Models\ResepBahan;
use Database\Seeders\BahanSeeder;
use Database\Seeders\ResepSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillTakaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_mengisi_satuan_dan_idempoten(): void
    {
        $this->seed(BahanSeeder::class);
        $this->seed(ResepSeeder::class);

        Schema::table('resep_bahans', function (Blueprint $table): void {
            $table->string('takaran_satuan', 20)->nullable()->change();
        });

        ResepBahan::query()->update([
            'takaran_jumlah' => null,
            'takaran_satuan' => null,
            'takaran_gram_ekuivalen' => null,
            'catatan_bahan' => null,
        ]);

        $this->assertSame(870, ResepBahan::whereNull('takaran_satuan')->count());

        $this->artisan('resep:backfill-takaran')->assertSuccessful();

        $this->assertSame(0, ResepBahan::whereNull('takaran_satuan')->count());

        $r040 = Resep::where('id_resep', 'R040')->firstOrFail();
        $kayuManis = $r040->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'Kayu manis'))->firstOrFail();
        $this->assertSame('secukupnya', $kayuManis->takaran_satuan);
        $this->assertNull($kayuManis->takaran_gram_ekuivalen);

        $r057 = Resep::where('id_resep', 'R057')->firstOrFail();
        $agar = $r057->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Agar%'))->firstOrFail();
        $this->assertSame('sdt', $agar->takaran_satuan);
        $this->assertEqualsWithDelta(5.0, (float) $agar->takaran_gram_ekuivalen, 0.01);

        $r029 = Resep::where('id_resep', 'R029')->firstOrFail();
        $kentang = $r029->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Kentang%'))->firstOrFail();
        $this->assertSame('g', $kentang->takaran_satuan);
        $this->assertEqualsWithDelta(150.0, (float) $kentang->takaran_gram_ekuivalen, 0.01);

        $this->artisan('resep:backfill-takaran')->assertSuccessful();
        $this->assertSame(0, ResepBahan::whereNull('takaran_satuan')->count());
    }
}
