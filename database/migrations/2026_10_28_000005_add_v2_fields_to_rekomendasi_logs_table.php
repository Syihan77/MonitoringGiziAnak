<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekomendasi_logs', function (Blueprint $table) {
            $table->uuid('resep_id')->nullable();
            $table->string('fokus_gizi', 50)->nullable();
            $table->integer('peringkat')->nullable();
            $table->decimal('skor_gizi_umum', 6, 3)->nullable();
            $table->decimal('skor_relevansi', 6, 3)->nullable();
            $table->decimal('skor_akhir', 6, 3)->nullable();
            $table->string('metode_ranking', 20)->nullable();
            $table->string('versi_model_atau_aturan', 50)->nullable();
            $table->text('alasan_peringkat')->nullable();
            $table->timestamp('tanggal_rekomendasi')->nullable();

            $table->index('resep_id');
            $table->index(['anak_id', 'tanggal_rekomendasi']);
        });

        DB::table('rekomendasi_logs')
            ->whereNull('tanggal_rekomendasi')
            ->update(['tanggal_rekomendasi' => DB::raw('created_at')]);

        if (DB::getDriverName() === 'pgsql') {
            Schema::table('rekomendasi_logs', function (Blueprint $table) {
                $table->foreign('resep_id')->references('id')->on('reseps')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('rekomendasi_logs', function (Blueprint $table) {
                $table->dropForeign(['resep_id']);
            });
        }

        Schema::table('rekomendasi_logs', function (Blueprint $table) {
            $table->dropIndex(['anak_id', 'tanggal_rekomendasi']);
            $table->dropIndex(['resep_id']);
            $table->dropColumn([
                'resep_id',
                'fokus_gizi',
                'peringkat',
                'skor_gizi_umum',
                'skor_relevansi',
                'skor_akhir',
                'metode_ranking',
                'versi_model_atau_aturan',
                'alasan_peringkat',
                'tanggal_rekomendasi',
            ]);
        });
    }
};
