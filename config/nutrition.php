<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Angka Kecukupan Gizi (Permenkes No. 28/2019, Tabel 1 & 3)
    |--------------------------------------------------------------------------
    |
    | Tanpa beda jenis kelamin untuk kelompok umur ini (sumber tidak
    | membedakan). Energi (kkal), protein/lemak/KH (g), besi/seng (mg).
    |
    */
    'akg' => [
        '0-5bln' => ['label' => '0–5 bulan', 'energi' => 550, 'protein' => 9, 'lemak' => 31, 'karbohidrat' => 59, 'zat_besi' => 0.3, 'seng' => 1.1],
        '6-11bln' => ['label' => '6–11 bulan', 'energi' => 800, 'protein' => 15, 'lemak' => 35, 'karbohidrat' => 105, 'zat_besi' => 11, 'seng' => 3],
        '1-3th' => ['label' => '1–3 tahun', 'energi' => 1350, 'protein' => 20, 'lemak' => 45, 'karbohidrat' => 215, 'zat_besi' => 7, 'seng' => 3],
        '4-6th' => ['label' => '4–6 tahun', 'energi' => 1400, 'protein' => 25, 'lemak' => 50, 'karbohidrat' => 220, 'zat_besi' => 10, 'seng' => 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambang status %AKG (keputusan proyek — SKPL tidak menetapkan angka)
    |--------------------------------------------------------------------------
    */
    'cutoff_cukup' => 80.0,
    'cutoff_kurang' => 70.0,
];
