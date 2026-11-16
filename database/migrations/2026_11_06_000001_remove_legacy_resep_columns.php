<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reseps', 'porsi_saji') && Schema::hasColumn('reseps', 'jumlah_porsi_hasil')) {
            DB::table('reseps')
                ->whereNull('jumlah_porsi_hasil')
                ->whereNotNull('porsi_saji')
                ->update(['jumlah_porsi_hasil' => DB::raw('porsi_saji')]);
        }

        if (
            Schema::hasColumn('resep_bahans', 'takaran_gram')
            && Schema::hasColumn('resep_bahans', 'takaran_gram_ekuivalen')
        ) {
            DB::table('resep_bahans')
                ->whereNull('takaran_gram_ekuivalen')
                ->whereNotNull('takaran_gram')
                ->update(['takaran_gram_ekuivalen' => DB::raw('takaran_gram')]);
        }

        Schema::table('bahan_makanans', function (Blueprint $table): void {
            $table->dropColumn('estimasi_harga_per100g');
        });

        Schema::table('reseps', function (Blueprint $table): void {
            $table->dropColumn(['estimasi_biaya', 'porsi_saji']);
        });

        Schema::table('resep_bahans', function (Blueprint $table): void {
            $table->dropColumn('takaran_gram');
        });
    }

    public function down(): void
    {
        Schema::table('bahan_makanans', function (Blueprint $table): void {
            $table->decimal('estimasi_harga_per100g', 8, 2)->nullable();
        });

        Schema::table('reseps', function (Blueprint $table): void {
            $table->decimal('estimasi_biaya', 8, 2)->nullable();
            $table->integer('porsi_saji')->nullable();
        });

        Schema::table('resep_bahans', function (Blueprint $table): void {
            $table->decimal('takaran_gram', 6, 2)->nullable();
        });
    }
};
