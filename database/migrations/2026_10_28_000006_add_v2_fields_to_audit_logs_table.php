<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('entitas_target', 50)->nullable();
            $table->uuid('entitas_id')->nullable();
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();

            $table->index(['entitas_target', 'entitas_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['entitas_target', 'entitas_id']);
            $table->dropColumn([
                'entitas_target',
                'entitas_id',
                'nilai_lama',
                'nilai_baru',
            ]);
        });
    }
};
