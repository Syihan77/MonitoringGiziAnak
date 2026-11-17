<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $null = DB::table('konsumsis')->whereNull('kondisi_id')->count();

        if ($null > 0) {
            throw new RuntimeException(
                "Tidak dapat membuat konsumsis.kondisi_id NOT NULL: {$null} baris masih NULL. "
                .'Backfill kondisi_id terlebih dahulu.'
            );
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            Schema::table('konsumsis', function (Blueprint $table) {
                $table->dropForeign(['kondisi_id']);
            });
        }

        Schema::table('konsumsis', function (Blueprint $table) {
            $table->uuid('kondisi_id')->nullable(false)->change();
        });

        if ($driver === 'pgsql') {
            Schema::table('konsumsis', function (Blueprint $table) {
                $table->foreign('kondisi_id')->references('id')->on('kondisi_harian')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            Schema::table('konsumsis', function (Blueprint $table) {
                $table->dropForeign(['kondisi_id']);
            });
        }

        Schema::table('konsumsis', function (Blueprint $table) {
            $table->uuid('kondisi_id')->nullable()->change();
        });

        if ($driver === 'pgsql') {
            Schema::table('konsumsis', function (Blueprint $table) {
                $table->foreign('kondisi_id')->references('id')->on('kondisi_harian')->nullOnDelete();
            });
        }
    }
};
