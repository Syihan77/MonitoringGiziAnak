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

    /*
    |--------------------------------------------------------------------------
    | Ambang klasifikasi Z-score (baseline sementara)
    |--------------------------------------------------------------------------
    |
    | Nilai ini mengikuti baseline Permenkes No. 2/2020 dan masih menunggu
    | validasi ahli gizi. Kategori pemantauan adalah rancangan sistem, bukan
    | diagnosis.
    |
    */
    'zscore' => [
        'versi_aturan' => 'permenkes-2-2020-baseline-v1',
        'umur_min_bulan' => 0,
        'umur_max_bulan' => 60,
        'ambang' => [
            'bb_u' => [
                'sangat_kurang_max' => -3.0,
                'kurang_max' => -2.0,
                'normal_max' => 1.0,
            ],
            'tb_u' => [
                'sangat_pendek_max' => -3.0,
                'pendek_max' => -2.0,
                'normal_max' => 3.0,
            ],
            'bb_tb' => [
                'gizi_buruk_max' => -3.0,
                'gizi_kurang_max' => -2.0,
                'normal_max' => 1.0,
                'berisiko_lebih_max' => 2.0,
                'gizi_lebih_max' => 3.0,
            ],
            'pemantauan' => [
                'high_risk_max' => -3.0,
                'at_risk_max' => -2.0,
                'bb_tb_at_risk_max' => 2.0,
                'bb_u_at_risk_max' => 1.0,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agregat dashboard kader
    |--------------------------------------------------------------------------
    |
    | Prevalensi memakai kategori_pemantauan (bukan diagnosis). Persentase
    | hanya bermakna bila denominator memadai; bulan tanpa data memakai null.
    |
    */
    'kader' => [
        'bulan_default' => 6,
        'bulan_maks' => 12,
        'denominator_min' => 5,
        'zona_waktu' => 'Asia/Jakarta',
    ],

    /*
    |--------------------------------------------------------------------------
    | Baseline Hybrid Recipe Ranking (KONF-11 belum final)
    |--------------------------------------------------------------------------
    |
    | Bobot dan versi aturan dibuat konfigurabel agar dapat diganti tanpa
    | mengubah struktur basis data. Filter verifikasi mengikuti SKPL-ML2-11.
    |
    */
    'rekomendasi' => [
        'versi_aturan' => 'hybrid-baseline-v1',
        'bobot_gizi_umum' => 0.6,
        'bobot_relevansi' => 0.4,
        'top_k_default' => 5,
        'top_k_maks' => 5,
        'demo_allow_data_lengkap' => (bool) env('NUTRITION_DEMO_ALLOW_DATA_LENGKAP', false),
    ],
];
