<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan pengukuran + hasil Z-score 3 indeks (SKPL §5.4.5, F-04).
     */
    public function up(): void
    {
        Schema::create('pertumbuhans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('anak_id')->index();
            $table->foreign('anak_id')->references('id')->on('anaks')->cascadeOnDelete();
            $table->date('tanggal_ukur');
            $table->integer('umur_bulan'); // bulan-penuh saat ukur
            $table->decimal('berat_kg', 5, 2);
            $table->decimal('tinggi_cm', 5, 1); // nilai mentah input
            $table->decimal('tinggi_koreksi_cm', 5, 1); // setelah koreksi ±0,7
            $table->string('posisi_ukur', 20)->nullable(); // terlentang | berdiri | null
            $table->decimal('z_bb_u', 5, 2)->nullable();
            $table->decimal('z_tb_u', 5, 2)->nullable();
            $table->decimal('z_bb_tb', 5, 2)->nullable();
            $table->string('status_bb_u', 50)->nullable();
            $table->string('status_tb_u', 50)->nullable();
            $table->string('status_bb_tb', 50)->nullable();
            $table->string('status_gizi_antropometri', 50);
            $table->uuid('dicatat_oleh')->nullable();
            $table->timestamps();

            $table->index(['anak_id', 'tanggal_ukur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pertumbuhans');
    }
};
