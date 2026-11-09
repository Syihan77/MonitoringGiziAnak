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
        Schema::table('label_rankings', function (Blueprint $table) {
            $table->foreign('label_sebelumnya_id')->references('id')->on('label_rankings')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('label_rankings', function (Blueprint $table) {
            $table->dropForeign(['label_sebelumnya_id']);
        });
    }
};
