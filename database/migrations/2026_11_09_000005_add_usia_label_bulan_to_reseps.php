<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseps', function (Blueprint $table): void {
            $table->unsignedSmallInteger('usia_label_bulan')->nullable()->after('usia_min_bulan');
        });

        DB::table('reseps')
            ->where('sumber_resep', 'Dataset Resep Gizi Terstandarisasi')
            ->whereNotNull('usia_min_bulan')
            ->update(['usia_label_bulan' => DB::raw('usia_min_bulan')]);

        DB::table('reseps')
            ->where('sumber_resep', 'Dataset Resep Gizi Terstandarisasi')
            ->whereNotNull('usia_label_bulan')
            ->update(['usia_min_bulan' => 12]);
    }

    public function down(): void
    {
        DB::table('reseps')
            ->whereNotNull('usia_label_bulan')
            ->update(['usia_min_bulan' => DB::raw('usia_label_bulan')]);

        Schema::table('reseps', function (Blueprint $table): void {
            $table->dropColumn('usia_label_bulan');
        });
    }
};
