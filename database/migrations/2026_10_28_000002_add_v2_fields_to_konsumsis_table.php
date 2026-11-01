<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konsumsis', function (Blueprint $table) {
            $table->uuid('kondisi_id')->nullable();
            $table->uuid('resep_id')->nullable();
            $table->decimal('porsi_resep_dikonsumsi', 4, 2)->nullable();
            $table->string('metode_input', 10)->default('MANUAL');
            $table->string('status_validitas', 10)->default('VALID');
            $table->string('alasan_invalid', 20)->nullable();

            $table->index('kondisi_id');
            $table->index('resep_id');
            $table->index(['anak_id', 'tanggal_konsumsi', 'status_validitas']);
        });

        if (DB::getDriverName() === 'pgsql') {
            Schema::table('konsumsis', function (Blueprint $table) {
                $table->foreign('kondisi_id')->references('id')->on('kondisi_harian')->nullOnDelete();
                $table->foreign('resep_id')->references('id')->on('reseps')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('konsumsis', function (Blueprint $table) {
                $table->dropForeign(['kondisi_id']);
                $table->dropForeign(['resep_id']);
            });
        }

        Schema::table('konsumsis', function (Blueprint $table) {
            $table->dropIndex(['anak_id', 'tanggal_konsumsi', 'status_validitas']);
            $table->dropIndex(['resep_id']);
            $table->dropIndex(['kondisi_id']);
            $table->dropColumn([
                'kondisi_id',
                'resep_id',
                'porsi_resep_dikonsumsi',
                'metode_input',
                'status_validitas',
                'alasan_invalid',
            ]);
        });
    }
};
