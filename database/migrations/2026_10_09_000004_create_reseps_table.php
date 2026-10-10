<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referensi resep (SKPL Tabel RESEP, F-12).
     * estimasi_biaya dihitung otomatis dari takaran × harga bahan;
     * NULL bila ada bahan tanpa info harga (deviasi terdokumentasi).
     */
    public function up(): void
    {
        Schema::create('reseps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_resep', 100);
            $table->text('cara_memasak');
            $table->string('kategori_gizi', 50);
            $table->decimal('estimasi_biaya', 8, 2)->nullable();
            $table->integer('porsi_saji');
            $table->string('tingkat_kesulitan', 20)->nullable();
            $table->timestamps();

            $table->index('kategori_gizi');
        });

        Schema::create('resep_bahans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('resep_id')->index();
            $table->foreign('resep_id')->references('id')->on('reseps')->cascadeOnDelete();
            $table->uuid('bahan_id')->index();
            $table->foreign('bahan_id')->references('id')->on('bahan_makanans')->restrictOnDelete();
            $table->decimal('takaran_gram', 6, 2);
            $table->timestamps();
        });

        Schema::create('rekomendasi_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('anak_id')->index();
            $table->foreign('anak_id')->references('id')->on('anaks')->cascadeOnDelete();
            $table->date('tanggal_minta');
            $table->json('masukan');
            $table->json('defisiensi');
            $table->integer('jumlah_hasil');
            $table->uuid('diminta_oleh')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_logs');
        Schema::dropIfExists('resep_bahans');
        Schema::dropIfExists('reseps');
    }
};
