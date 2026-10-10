<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pisahkan "jenis kunci" (dasar) dari "varian tabel" (pb vs tb).
     *
     * dasar  = 'umur' | 'panjang' (kolom kunci yang dipakai).
     * varian = null (bb_u) | 'pb' (rujukan panjang, 0-24 bln)
     *        | 'tb' (rujukan tinggi, > 24 bln).
     */
    public function up(): void
    {
        Schema::table('who_standards', function (Blueprint $table) {
            $table->string('varian', 10)->nullable()->after('dasar');
            $table->index(['indikator', 'jenis_kelamin', 'varian', 'umur_bulan']);
            $table->index(['indikator', 'jenis_kelamin', 'varian', 'panjang_cm']);
        });
    }

    public function down(): void
    {
        Schema::table('who_standards', function (Blueprint $table) {
            $table->dropIndex(['indikator', 'jenis_kelamin', 'varian', 'umur_bulan']);
            $table->dropIndex(['indikator', 'jenis_kelamin', 'varian', 'panjang_cm']);
            $table->dropColumn('varian');
        });
    }
};
