<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baku rujukan WHO/Kemenkes (Permenkes No. 2/2020, Tabel 1-5 & 8-12).
     * Hanya 3 indeks (keputusan proyek): bb_u, tb_u, bb_tb — tanpa IMT/U.
     *
     * dasar = 'umur'  -> baris keyed by umur_bulan (bb_u, tb_u).
     * dasar = 'panjang' -> baris keyed by panjang_cm (bb_tb).
     * varian (ditambah migrasi lanjutan) = null | 'pb' | 'tb'.
     */
    public function up(): void
    {
        Schema::create('who_standards', function (Blueprint $table) {
            $table->id();
            $table->string('indikator', 10); // bb_u | tb_u | bb_tb
            $table->char('jenis_kelamin', 1); // L | P
            $table->string('dasar', 10); // umur | pb | tb
            $table->integer('umur_bulan')->nullable();
            $table->decimal('panjang_cm', 5, 1)->nullable();
            $table->decimal('sd_minus3', 7, 2);
            $table->decimal('sd_minus2', 7, 2);
            $table->decimal('sd_minus1', 7, 2);
            $table->decimal('median', 7, 2);
            $table->decimal('sd_plus1', 7, 2);
            $table->decimal('sd_plus2', 7, 2);
            $table->decimal('sd_plus3', 7, 2);
            $table->timestamps();

            $table->index(['indikator', 'jenis_kelamin', 'dasar', 'umur_bulan']);
            $table->index(['indikator', 'jenis_kelamin', 'dasar', 'panjang_cm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('who_standards');
    }
};
