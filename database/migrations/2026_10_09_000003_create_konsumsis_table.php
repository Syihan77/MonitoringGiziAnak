<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konsumsi harian per bahan (SKPL Tabel KONSUMSI_HARIAN, F-06).
     * Porsi selalu disimpan dalam gram (input kg dikonversi di controller).
     */
    public function up(): void
    {
        Schema::create('konsumsis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('anak_id')->index();
            $table->foreign('anak_id')->references('id')->on('anaks')->cascadeOnDelete();
            $table->uuid('bahan_id')->index();
            $table->foreign('bahan_id')->references('id')->on('bahan_makanans')->restrictOnDelete();
            $table->decimal('porsi_gram', 6, 2);
            $table->date('tanggal_konsumsi');
            $table->uuid('dicatat_oleh')->nullable();
            $table->timestamps();

            $table->index(['anak_id', 'tanggal_konsumsi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konsumsis');
    }
};
