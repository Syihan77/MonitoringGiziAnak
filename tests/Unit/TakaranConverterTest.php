<?php

namespace Tests\Unit;

use App\Support\TakaranConverter;
use Tests\TestCase;

class TakaranConverterTest extends TestCase
{
    public function test_takaran_rumah_tangga_dan_pecahan(): void
    {
        $nasi = TakaranConverter::parse('Nasi: ¾ gls (50 gr)');
        $this->assertSame('Nasi', $nasi['nama']);
        $this->assertEqualsWithDelta(0.75, $nasi['jumlah'], 0.001);
        $this->assertSame('gls', $nasi['satuan']);
        $this->assertEqualsWithDelta(50.0, $nasi['gram'], 0.01);

        $bawang = TakaranConverter::parse('Bawang putih (bumbu halus): 2 siung');
        $this->assertEqualsWithDelta(8.0, $bawang['gram'], 0.01);

        $gula = TakaranConverter::parse('Gula pasir: 1,5 sdt');
        $this->assertEqualsWithDelta(7.5, $gula['gram'], 0.01);
    }

    public function test_gram_eksplisit_mengalahkan_konversi_satuan(): void
    {
        $tahu = TakaranConverter::parse('Tahu putih potong dadu kecil: 120 g (3 buah sedang)');
        $this->assertEqualsWithDelta(120.0, $tahu['gram'], 0.01);
        $this->assertSame('g', $tahu['satuan']);

        $ikan = TakaranConverter::parse('Tuna fillet, potong dadu: 1/3 ekor sedang (30 gr)');
        $this->assertEqualsWithDelta(30.0, $ikan['gram'], 0.01);
    }

    public function test_titik_dua_dalam_kurung_tidak_memecah_nama_bahan(): void
    {
        $air = TakaranConverter::parse('Air (perbandingan beras dan air 1:4)');
        $this->assertSame('Air (perbandingan beras dan air 1:4)', $air['nama']);
        $this->assertNull($air['gram']);
        $this->assertTrue(TakaranConverter::pelengkap($air['nama']));
    }

    public function test_pelengkap_dan_bahan_utama_dibedakan(): void
    {
        $this->assertTrue(TakaranConverter::pelengkap('Bawang merah goreng'));
        $this->assertTrue(TakaranConverter::pelengkap('Santan cair'));
        $this->assertFalse(TakaranConverter::pelengkap('Nasi putih'));
        $this->assertFalse(TakaranConverter::pelengkap('Ikan tuna'));
    }
}
