<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseps', function (Blueprint $table): void {
            $table->string('nama_pemverifikasi', 100)->nullable();
            $table->date('tanggal_verifikasi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reseps', function (Blueprint $table): void {
            $table->dropColumn(['nama_pemverifikasi', 'tanggal_verifikasi']);
        });
    }
};
