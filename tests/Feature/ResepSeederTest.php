<?php

namespace Tests\Feature;

use App\Models\Resep;
use App\Models\ResepBahan;
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
        $this->assertSame(62, Resep::where('status_verifikasi_data', 'DATA_LENGKAP')->count());
        $this->assertSame(31, Resep::where('status_verifikasi_data', 'PERLU_VERIFIKASI')->count());
        $this->assertSame(0, Resep::doesntHave('bahans')->count());
        $this->assertSame(0, Resep::where('status_verifikasi_data', Resep::VERIFIKASI_TERVERIFIKASI)->count());
        $this->assertSame(0, Resep::where(fn ($q) => $q
            ->whereNull('usia_min_bulan')
            ->orWhere('usia_min_bulan', '!=', 12))->count());

        $r001 = Resep::where('id_resep', 'R001')->firstOrFail();
        $this->assertSame('PERLU_VERIFIKASI', $r001->status_verifikasi_data);
        $this->assertGreaterThanOrEqual(1, $r001->jumlah_porsi_hasil);
        $this->assertContains('telur', $r001->alergen);

        $idGiziKosong = [
            'R001', 'R007', 'R019', 'R022', 'R026', 'R028', 'R041',
            'R048', 'R050', 'R053', 'R056', 'R077', 'R083', 'R092',
        ];
        $resepKosong = Resep::whereIn('id_resep', $idGiziKosong)->get();

        $this->assertCount(14, $resepKosong);
        foreach ($resepKosong as $resep) {
            foreach (array_keys($resep->giziPerPorsi()) as $zat) {
                $this->assertNull($resep->giziPerPorsi()[$zat], "{$resep->id_resep} {$zat}");
            }
            $this->assertSame('PERLU_VERIFIKASI', $resep->status_verifikasi_data);
            $this->assertStringContainsString(
                Resep::CATATAN_MAKRO_TIDAK_LENGKAP,
                (string) $resep->catatan_resep,
            );
            $this->assertSame(1, substr_count((string) $resep->catatan_resep, Resep::CATATAN_MAKRO_TIDAK_LENGKAP));
        }

        $r027 = Resep::where('id_resep', 'R027')->firstOrFail();
        $this->assertSame(12, $r027->usia_min_bulan);
        $this->assertSame(6, $r027->usia_label_bulan);

        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R057')->value('status_verifikasi_data'));
        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R061')->value('status_verifikasi_data'));
        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R079')->value('status_verifikasi_data'));
        $this->assertSame('PERLU_VERIFIKASI', Resep::where('id_resep', 'R093')->value('status_verifikasi_data'));
        $this->assertSame('DATA_LENGKAP', Resep::where('id_resep', 'R067')->value('status_verifikasi_data'));
        $this->assertSame('DATA_LENGKAP', Resep::where('id_resep', 'R091')->value('status_verifikasi_data'));

        $r009 = Resep::where('id_resep', 'R009')->firstOrFail();
        $this->assertEqualsWithDelta(160.65, (float) $r009->energi_per_porsi, 0.001);
        $this->assertEqualsWithDelta(40.5, (float) $r009->kalsium_per_porsi, 0.001);

        $r024 = Resep::where('id_resep', 'R024')->firstOrFail();
        $this->assertEqualsWithDelta(32.15, (float) $r024->kalsium_per_porsi, 0.001);
        $this->assertEqualsWithDelta(0.74, (float) $r024->zinc_per_porsi, 0.001);

        $r031 = Resep::where('id_resep', 'R031')->firstOrFail();
        $this->assertEqualsWithDelta(142.85, (float) $r031->kalsium_per_porsi, 0.001);
        $this->assertEqualsWithDelta(1.67, (float) $r031->zinc_per_porsi, 0.001);
        $this->assertNull($r031->vitamin_c_per_porsi);

        $r058 = Resep::where('id_resep', 'R058')->firstOrFail();
        $this->assertEqualsWithDelta(79.66, (float) $r058->kalsium_per_porsi, 0.001);
        $this->assertEqualsWithDelta(1.06, (float) $r058->zinc_per_porsi, 0.001);
        $this->assertNull($r058->vitamin_c_per_porsi);

        $r063 = Resep::where('id_resep', 'R063')->firstOrFail();
        $this->assertEqualsWithDelta(35.95, (float) $r063->kalsium_per_porsi, 0.001);
        $this->assertEqualsWithDelta(0.33, (float) $r063->zinc_per_porsi, 0.001);
        $this->assertEqualsWithDelta(1.46, (float) $r063->serat_per_porsi, 0.001);

        $r084 = Resep::where('id_resep', 'R084')->firstOrFail();
        $this->assertEqualsWithDelta(109.11, (float) $r084->kalsium_per_porsi, 0.001);
        $this->assertEqualsWithDelta(0.5, (float) $r084->zinc_per_porsi, 0.001);

        $r057 = Resep::where('id_resep', 'R057')->firstOrFail();
        $this->assertEqualsWithDelta(66.2, (float) $r057->kalsium_per_porsi, 0.001);
        $this->assertEqualsWithDelta(0.4, (float) $r057->zinc_per_porsi, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $r057->vitamin_a_per_porsi, 0.001);

        $this->assertStringContainsString('Gizi dihitung dari TKPI 2020 (estimasi), belum diverifikasi ahli gizi', (string) $r058->catatan_resep);
        $this->assertStringNotContainsString('Catatan data:', (string) $r058->catatan_resep);
        $this->assertStringNotContainsString('normalisasi basis ML', (string) $r058->catatan_resep);

        $r032 = Resep::where('id_resep', 'R032')->firstOrFail();
        $tahu = $r032->bahans()
            ->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Tahu putih%'))
            ->firstOrFail();
        $this->assertEqualsWithDelta(120.0, (float) $tahu->takaran_gram_ekuivalen, 0.01);

        $this->assertSame(0, ResepBahan::where(fn ($q) => $q
            ->whereNull('takaran_satuan')
            ->orWhere('takaran_satuan', ''))->count());

        $r040 = Resep::where('id_resep', 'R040')->firstOrFail();
        $this->assertSame('PERLU_VERIFIKASI', $r040->status_verifikasi_data);
        $kayuManis = $r040->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'Kayu manis'))->firstOrFail();
        $this->assertSame('secukupnya', $kayuManis->takaran_satuan);
        $this->assertNull($kayuManis->takaran_gram_ekuivalen);
        $this->assertStringContainsString('perlu verifikasi', (string) $kayuManis->catatan_bahan);
        $this->assertStringNotContainsString('1 cm', (string) $kayuManis->catatan_bahan);

        $r042 = Resep::where('id_resep', 'R042')->firstOrFail();
        $kelapa = $r042->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'Kelapa parut'))->firstOrFail();
        $this->assertSame('g', $kelapa->takaran_satuan);
        $this->assertEqualsWithDelta(50.0, (float) $kelapa->takaran_gram_ekuivalen, 0.01);
        $this->assertNull($kelapa->catatan_bahan);

        $r046 = Resep::where('id_resep', 'R046')->firstOrFail();
        $soun = $r046->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Soun%'))->firstOrFail();
        $this->assertSame('g', $soun->takaran_satuan);
        $this->assertEqualsWithDelta(60.0, (float) $soun->takaran_gram_ekuivalen, 0.01);
        $this->assertNull($soun->catatan_bahan);

        $r048 = Resep::where('id_resep', 'R048')->firstOrFail();
        foreach ([['Bawang putih haluskan', 12.0], ['Bawang merah haluskan', 15.0]] as [$nama, $gram]) {
            $bawang = $r048->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', $nama))->firstOrFail();
            $this->assertSame('siung', $bawang->takaran_satuan);
            $this->assertEqualsWithDelta($gram, (float) $bawang->takaran_gram_ekuivalen, 0.01);
            $this->assertNull($bawang->catatan_bahan);
        }

        $r057 = Resep::where('id_resep', 'R057')->firstOrFail();
        $this->assertSame(12, $r057->usia_min_bulan);
        $this->assertSame(12, $r057->usia_label_bulan);
        $agar = $r057->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Agar%'))->firstOrFail();
        $this->assertSame('sdt', $agar->takaran_satuan);
        $this->assertEqualsWithDelta(5.0, (float) $agar->takaran_gram_ekuivalen, 0.01);
        $this->assertNull($agar->catatan_bahan);

        $r029 = Resep::where('id_resep', 'R029')->firstOrFail();
        $kentang = $r029->bahans()->whereHas('bahan', fn ($q) => $q->where('nama_bahan', 'like', 'Kentang%'))->firstOrFail();
        $this->assertSame('g', $kentang->takaran_satuan);
        $this->assertEqualsWithDelta(150.0, (float) $kentang->takaran_gram_ekuivalen, 0.01);

        $this->artisan('resep:backfill-takaran')->assertSuccessful();

        $jumlahBahan = $r001->bahans()->count();

        $this->seed(ResepSeeder::class);

        $this->assertSame(93, Resep::count());
        $this->assertSame(62, Resep::where('status_verifikasi_data', 'DATA_LENGKAP')->count());
        $this->assertSame(31, Resep::where('status_verifikasi_data', 'PERLU_VERIFIKASI')->count());
        $this->assertSame($jumlahBahan, $r001->fresh()->bahans()->count());
        $this->assertSame(
            1,
            substr_count((string) $r001->fresh()->catatan_resep, Resep::CATATAN_MAKRO_TIDAK_LENGKAP),
        );
    }

    public function test_basis_ml2_cocok_dengan_data_tkpi(): void
    {
        $this->seed(BahanSeeder::class);
        $this->seed(ResepSeeder::class);

        $this->artisan('ml:verify-basis')
            ->expectsOutputToContain('Baris CSV: 93 | cocok: 93 | selisih: 0')
            ->expectsOutputToContain('Selisih per zat:')
            ->assertSuccessful();
    }
}
