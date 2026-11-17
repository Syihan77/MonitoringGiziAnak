<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skenario_rankings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('usia_bulan');
            $table->string('fokus_gizi', 50)->nullable();
            $table->string('pantangan', 200)->nullable();
            $table->string('bahan_tersedia', 500)->nullable();
            $table->boolean('is_sintesis');
            $table->string('versi_rubrik', 50);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skenario_rankings');
    }
};
