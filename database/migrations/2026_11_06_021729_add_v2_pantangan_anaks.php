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
        Schema::table('pantangan_anaks', function (Blueprint $table) {
            $table->dropColumn('alergen');
            $table->dropColumn('jenis');

            $table->enum('jenis', ['ALERGI', 'PANTANGAN']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pantangan_anaks');
    }
};
