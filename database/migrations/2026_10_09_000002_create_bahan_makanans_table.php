<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referensi bahan makanan (SKPL Tabel BAHAN_MAKANAN, F-11).
     * Deviasi terdokumentasi: kolom gizi NULLABLE (TKPI 2020 memiliki sel
     * kosong = data tidak tersedia; analisis memperlakukannya sebagai 0)
     * dan estimasi_harga_per100g NULLABLE (belum ada sumber harga).
     */
    public function up(): void
    {
        Schema::create('bahan_makanans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_bahan', 100)->unique();
            $table->string('kelompok_gizi', 50);
            // Tambahan di luar kamus SKPL (perlu untuk %AKG energi).
            $table->decimal('energi_per100g', 7, 2)->nullable();
            $table->decimal('karbohidrat_per100g', 6, 2)->nullable();
            $table->decimal('protein_per100g', 6, 2)->nullable();
            $table->decimal('lemak_per100g', 6, 2)->nullable();
            $table->decimal('zat_besi_per100g', 6, 2)->nullable();
            $table->decimal('zinc_per100g', 6, 2)->nullable();
            $table->decimal('estimasi_harga_per100g', 8, 2)->nullable();
            $table->string('sumber_rujukan', 100)->nullable();
            $table->timestamps();

            $table->index('kelompok_gizi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_makanans');
    }
};
