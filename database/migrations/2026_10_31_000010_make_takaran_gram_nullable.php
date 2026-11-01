<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE resep_bahans ALTER COLUMN takaran_gram DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('resep_bahans')
            ->whereNull('takaran_gram')
            ->delete();

        DB::statement('ALTER TABLE resep_bahans ALTER COLUMN takaran_gram SET NOT NULL');
    }
};
