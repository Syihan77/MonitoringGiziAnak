<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_legacy_biaya_dan_takaran_sudah_tidak_ada(): void
    {
        $this->assertFalse(Schema::hasColumn('bahan_makanans', 'estimasi_harga_per100g'));
        $this->assertFalse(Schema::hasColumn('reseps', 'estimasi_biaya'));
        $this->assertFalse(Schema::hasColumn('reseps', 'porsi_saji'));
        $this->assertFalse(Schema::hasColumn('resep_bahans', 'takaran_gram'));
    }

    public function test_kolom_legacy_antropometri_dan_defisiensi_sudah_tidak_ada(): void
    {
        $this->assertFalse(Schema::hasColumn('pertumbuhans', 'status_gizi_antropometri'));
        $this->assertFalse(Schema::hasColumn('rekomendasi_logs', 'defisiensi'));
    }

    public function test_label_usia_resep_terpisah_dari_usia_minimal(): void
    {
        $this->assertTrue(Schema::hasColumn('reseps', 'usia_label_bulan'));
        $this->assertTrue(Schema::hasColumn('reseps', 'usia_min_bulan'));
    }

    public function test_kolom_verifikasi_resep_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('reseps', 'nama_pemverifikasi'));
        $this->assertTrue(Schema::hasColumn('reseps', 'tanggal_verifikasi'));
    }

    public function test_kolom_gizi_per_porsi_resep_nullable(): void
    {
        $kolom = collect(Schema::getColumns('reseps'))->keyBy('name');

        foreach ([
            'energi_per_porsi',
            'karbohidrat_per_porsi',
            'protein_per_porsi',
            'lemak_per_porsi',
            'zat_besi_per_porsi',
            'vitamin_c_per_porsi',
            'kalsium_per_porsi',
            'zinc_per_porsi',
            'vitamin_a_per_porsi',
            'serat_per_porsi',
        ] as $nama) {
            $this->assertNotNull($kolom->get($nama), "Kolom {$nama} tidak ditemukan.");
            $this->assertTrue($kolom->get($nama)['nullable'], "Kolom {$nama} harus nullable.");
        }
    }

    public function test_takaran_satuan_wajib_diisi(): void
    {
        $kolom = collect(Schema::getColumns('resep_bahans'))
            ->firstWhere('name', 'takaran_satuan');

        $this->assertNotNull($kolom);
        $this->assertFalse($kolom['nullable']);
    }

    public function test_kondisi_id_konsumsi_wajib_diisi(): void
    {
        $kolom = collect(Schema::getColumns('konsumsis'))
            ->firstWhere('name', 'kondisi_id');

        $this->assertNotNull($kolom);
        $this->assertFalse($kolom['nullable']);
    }
}
