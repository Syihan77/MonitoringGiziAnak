<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseps', function (Blueprint $table) {
            $table->string('id_resep', 20)->nullable();
            $table->unique('id_resep');
        });
    }

    public function down(): void
    {
        Schema::table('reseps', function (Blueprint $table) {
            $table->dropUnique(['id_resep']);
            $table->dropColumn('id_resep');
        });
    }
};
