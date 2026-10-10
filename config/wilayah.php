<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daftar Wilayah Posyandu
    |--------------------------------------------------------------------------
    |
    | GANTI dengan data resmi desa sebelum pilot. Setiap nama harus unik dan
    | ditulis persis seperti yang dipakai kader (contoh: "Posyandu Melati").
    | Registrasi kader memvalidasi `wilayah_posyandu` terhadap daftar ini.
    |
    */
    'desa' => env('DESA_NAMA', null),

    'daftar' => [
        // CONTOH AWAL — hapus/ganti dengan daftar resmi desa.
        'Posyandu Melati',
        'Posyandu Mawar',
    ],
];
