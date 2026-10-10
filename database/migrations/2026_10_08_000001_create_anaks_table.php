<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil anak milik pengguna (SKPL §5.4.4, F-03).
     * Tanpa NIK (keputusan proyek): identitas = nama + tanggal_lahir + pemilik.
     */
    public function up(): void
    {
        Schema::create('anaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('nama', 100);
            $table->date('tanggal_lahir');
            $table->char('jenis_kelamin', 1); // L | P (SKPL)
            $table->text('alamat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anaks');
    }
};
