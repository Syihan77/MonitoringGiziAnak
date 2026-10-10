<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master wilayah posyandu (Fase 2).
     * Nama dinormalisasi di satu tempat agar agregat dashboard desa valid
     * (tanpa varian ejaan "Melati" vs "melati"). Diisi via WilayahSeeder
     * dari config/wilayah.php (diganti dengan data resmi desa).
     */
    public function up(): void
    {
        Schema::create('wilayahs', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->string('desa', 100)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayahs');
    }
};
