<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertumbuhans', function (Blueprint $table) {
            $table->decimal('usia_bulan_saat_ukur', 5, 2)->nullable();
            $table->string('kategori_pemantauan', 30)->nullable();
            $table->string('metode_klasifikasi', 20)->nullable();
            $table->string('versi_aturan', 50)->nullable();
            $table->string('status_validitas_pengukuran', 20)->default('VALID');
            $table->text('alasan_pengecualian')->nullable();

            $table->index('kategori_pemantauan');
            $table->index('status_validitas_pengukuran');
        });

        DB::table('pertumbuhans')
            ->whereNull('usia_bulan_saat_ukur')
            ->update(['usia_bulan_saat_ukur' => DB::raw('umur_bulan')]);
    }

    public function down(): void
    {
        Schema::table('pertumbuhans', function (Blueprint $table) {
            $table->dropIndex(['status_validitas_pengukuran']);
            $table->dropIndex(['kategori_pemantauan']);
            $table->dropColumn([
                'usia_bulan_saat_ukur',
                'kategori_pemantauan',
                'metode_klasifikasi',
                'versi_aturan',
                'status_validitas_pengukuran',
                'alasan_pengecualian',
            ]);
        });
    }
};
