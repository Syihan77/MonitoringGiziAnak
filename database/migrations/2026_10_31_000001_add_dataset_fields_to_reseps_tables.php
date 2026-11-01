<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseps', function (Blueprint $table) {
            $table->json('alergen')->nullable();
            $table->tinyInteger('usia_minimal_bulan_jika_dimodifikasi')->nullable();
            $table->string('modifikasi_untuk_turunkan_usia', 200)->nullable();
            $table->text('catatan_resep')->nullable();
            $table->string('keyakinan_label', 20)->nullable();
            $table->string('jenis_menu', 50)->nullable();
        });

        Schema::table('reseps', function (Blueprint $table) {
            DB::statement('ALTER TABLE reseps ALTER COLUMN porsi_saji DROP NOT NULL');
        });

        Schema::table('reseps', function (Blueprint $table) {
            $table->string('status_verifikasi_data', 20)->default('PERLU_VERIFIKASI')->change();
        });

        DB::table('reseps')
            ->where('status_verifikasi_data', 'TERVERIFIKASI')
            ->update(['status_verifikasi_data' => 'DATA_LENGKAP']);

        Schema::create('pantangan_anaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('anak_id')->index();
            $table->foreign('anak_id')->references('id')->on('anaks')->cascadeOnDelete();
            $table->uuid('bahan_id')->nullable()->index();
            $table->foreign('bahan_id')->references('id')->on('bahan_makanans')->restrictOnDelete();
            $table->string('alergen', 50)->nullable();
            $table->string('jenis', 20)->default('ALERGI');
            $table->timestamps();

            $table->index('jenis');
            $table->unique(['anak_id', 'bahan_id', 'alergen', 'jenis'], 'pantangan_anaks_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pantangan_anaks');

        DB::table('reseps')
            ->where('status_verifikasi_data', 'DATA_LENGKAP')
            ->update(['status_verifikasi_data' => 'TERVERIFIKASI']);

        DB::table('reseps')
            ->whereNull('porsi_saji')
            ->update(['porsi_saji' => 1]);

        Schema::table('reseps', function (Blueprint $table) {
            $table->dropColumn([
                'alergen',
                'usia_minimal_bulan_jika_dimodifikasi',
                'modifikasi_untuk_turunkan_usia',
                'catatan_resep',
                'keyakinan_label',
                'jenis_menu',
            ]);
        });
    }
};
