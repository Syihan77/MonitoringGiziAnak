<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('label_rankings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('resep_id')->index();
            $table->foreign('resep_id')->references('id')->on('reseps')->cascadeOnDelete();
            $table->uuid('skenario_id')->index();
            $table->foreign('skenario_id')->references('id')->on('skenario_rankings')->cascadeOnDelete();
            $table->decimal('skor_gizi_umum', 6, 3);
            $table->decimal('skor_relevansi', 6, 3);
            $table->integer('peringkat_label');
            $table->text('alasan_skor');
            $table->enum('status_validasi', ['BELUM_DITINJAU', 'DISETUJUI', 'DIREVISI', 'DITOLAK', 'PERLU_DISKUSI']);
            $table->text('catatan_ahli')->nullable();
            $table->uuid('label_sebelumnya_id')->nullable()->index();

            $table->enum('jenis_label', ['SINTETIS_AWAL', 'SETELAH_VALIDASI_AHLI']);
            
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_rankings');
    }
};
