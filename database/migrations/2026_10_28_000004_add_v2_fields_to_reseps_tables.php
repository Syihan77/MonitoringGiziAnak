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
            $table->integer('jumlah_porsi_hasil')->nullable();
            $table->decimal('energi_per_porsi', 8, 2)->nullable();
            $table->decimal('protein_per_porsi', 8, 2)->nullable();
            $table->decimal('lemak_per_porsi', 8, 2)->nullable();
            $table->decimal('karbohidrat_per_porsi', 8, 2)->nullable();
            $table->decimal('serat_per_porsi', 8, 2)->nullable();
            $table->decimal('kalsium_per_porsi', 8, 2)->nullable();
            $table->decimal('zat_besi_per_porsi', 8, 2)->nullable();
            $table->decimal('zinc_per_porsi', 8, 2)->nullable();
            $table->decimal('vitamin_a_per_porsi', 8, 2)->nullable();
            $table->decimal('vitamin_c_per_porsi', 8, 2)->nullable();
            $table->tinyInteger('usia_min_bulan')->nullable();
            $table->tinyInteger('usia_maks_bulan')->nullable();
            $table->string('tekstur_sajian', 50)->nullable();
            $table->string('sumber_resep', 200)->nullable();
            $table->string('status_verifikasi_data', 20)->default('PERLU_VERIFIKASI');

            $table->index('status_verifikasi_data');
            $table->index(['usia_min_bulan', 'usia_maks_bulan']);
        });

        Schema::table('resep_bahans', function (Blueprint $table) {
            $table->decimal('takaran_jumlah', 8, 2)->nullable();
            $table->string('takaran_satuan', 20)->nullable();
            $table->decimal('takaran_gram_ekuivalen', 8, 2)->nullable();
            $table->string('catatan_bahan', 200)->nullable();
        });

        DB::table('reseps')
            ->whereNull('jumlah_porsi_hasil')
            ->update(['jumlah_porsi_hasil' => DB::raw('porsi_saji')]);

        DB::table('resep_bahans')
            ->whereNull('takaran_gram_ekuivalen')
            ->update([
                'takaran_jumlah' => DB::raw('takaran_gram'),
                'takaran_satuan' => 'g',
                'takaran_gram_ekuivalen' => DB::raw('takaran_gram'),
            ]);
    }

    public function down(): void
    {
        Schema::table('resep_bahans', function (Blueprint $table) {
            $table->dropColumn([
                'takaran_jumlah',
                'takaran_satuan',
                'takaran_gram_ekuivalen',
                'catatan_bahan',
            ]);
        });

        Schema::table('reseps', function (Blueprint $table) {
            $table->dropIndex(['usia_min_bulan', 'usia_maks_bulan']);
            $table->dropIndex(['status_verifikasi_data']);
            $table->dropColumn([
                'jumlah_porsi_hasil',
                'energi_per_porsi',
                'protein_per_porsi',
                'lemak_per_porsi',
                'karbohidrat_per_porsi',
                'serat_per_porsi',
                'kalsium_per_porsi',
                'zat_besi_per_porsi',
                'zinc_per_porsi',
                'vitamin_a_per_porsi',
                'vitamin_c_per_porsi',
                'usia_min_bulan',
                'usia_maks_bulan',
                'tekstur_sajian',
                'sumber_resep',
                'status_verifikasi_data',
            ]);
        });
    }
};
