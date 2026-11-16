<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE pantangan_anaks ADD CONSTRAINT pantangan_anaks_sumber_check '
            .'CHECK (bahan_id IS NOT NULL OR alergen IS NOT NULL)'
        );

        DB::statement(
            'ALTER TABLE pantangan_anaks ADD CONSTRAINT pantangan_anaks_jenis_check '
            ."CHECK (jenis IN ('ALERGI', 'PANTANGAN'))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE pantangan_anaks DROP CONSTRAINT IF EXISTS pantangan_anaks_sumber_check');
        DB::statement('ALTER TABLE pantangan_anaks DROP CONSTRAINT IF EXISTS pantangan_anaks_jenis_check');
    }
};
