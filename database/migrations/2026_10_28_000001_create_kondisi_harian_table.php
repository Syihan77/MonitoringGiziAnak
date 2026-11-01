<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kondisi_harian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('anak_id')->constrained('anaks')->cascadeOnDelete();
            $table->date('tanggal_kondisi');
            $table->string('status_kondisi', 20);
            $table->foreignUuid('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['anak_id', 'tanggal_kondisi']);
            $table->index('status_kondisi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kondisi_harian');
    }
};
