<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertumbuhans', function (Blueprint $table): void {
            $table->string('status_gizi_antropometri', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pertumbuhans', function (Blueprint $table): void {
            $table->string('status_gizi_antropometri', 50)->nullable(false)->change();
        });
    }
};
