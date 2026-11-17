<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('resep_bahans')->whereNull('takaran_satuan')->exists()) {
            throw new RuntimeException(
                'Masih ada resep_bahans.takaran_satuan null. Jalankan php artisan resep:backfill-takaran terlebih dahulu.'
            );
        }

        Schema::table('resep_bahans', function (Blueprint $table): void {
            $table->string('takaran_satuan', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('resep_bahans', function (Blueprint $table): void {
            $table->string('takaran_satuan', 20)->nullable()->change();
        });
    }
};
