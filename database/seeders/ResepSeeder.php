<?php

namespace Database\Seeders;

use App\Models\BahanMakanan;
use App\Models\Resep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 3 resep awal diport dari mock frontend (resources/js/input-ortu.js).
 * Takaran gram = estimasi dari berat mock ("120g", "1 ikat" ~= 100g, dst).
 * Bahan dicocokkan ke TKPI bila ada, jika tidak dibuat minimal (gizi NULL).
 */
class ResepSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::reseps() as $r) {
                $resep = Resep::updateOrCreate(
                    ['nama_resep' => $r['nama_resep']],
                    [
                        'cara_memasak' => implode("\n", $r['langkah']),
                        'kategori_gizi' => $r['kategori_gizi'],
                        'porsi_saji' => 1,
                        'tingkat_kesulitan' => 'Mudah',
                    ]
                );

                $resep->bahans()->delete();

                foreach ($r['bahans'] as [$nama, $kelompok, $gram]) {
                    $bahan = self::cariAtauBuatBahan($nama, $kelompok);
                    $resep->bahans()->create(['bahan_id' => $bahan->id, 'takaran_gram' => $gram]);
                }

                $resep->update(['estimasi_biaya' => $resep->hitungUlangBiaya()]);
            }
        });
    }

    private static function cariAtauBuatBahan(string $nama, string $kelompok): BahanMakanan
    {
        $cocok = BahanMakanan::query()
            ->where('nama_bahan', 'ilike', '%'.mb_substr($nama, 0, 12).'%')
            ->orderByRaw('LENGTH(nama_bahan)')
            ->first();

        if ($cocok !== null) {
            return $cocok;
        }

        return BahanMakanan::create([
            'nama_bahan' => $nama,
            'kelompok_gizi' => $kelompok,
            'sumber_rujukan' => 'Seed resep awal',
        ]);
    }

    /**
     * @return list<array{nama_resep: string, kategori_gizi: string, langkah: list<string>, bahans: list<array{0: string, 1: string, 2: float}>}>
     */
    private static function reseps(): array
    {
        return [
            [
                'nama_resep' => 'Omelet Bayam & Roti Gandum',
                'kategori_gizi' => 'tinggi protein',
                'langkah' => [
                    'Cuci bersih bayam, lalu rebus sebentar dan tiriskan. Cincang halus.',
                    'Kocok 2 butir telur dalam mangkuk, tambahkan bayam cincang dan sejumput garam.',
                    'Panaskan wajan dengan api kecil, olesi tipis dengan minyak zaitun.',
                    'Tuang adonan telur ke wajan, masak selama 3-4 menit hingga setengah matang.',
                    'Letakkan roti gandum di atas omelet, lipat menjadi dua.',
                    'Masak selama 2 menit lagi hingga matang sempurna. Angkat dan sajikan hangat.',
                ],
                'bahans' => [
                    ['Telur ayam', 'Protein Hewani', 120.0],
                    ['Bayam', 'Sayuran', 100.0],
                    ['Roti gandum', 'Serealia', 60.0],
                    ['Minyak zaitun', 'Lemak', 5.0],
                    ['Garam', 'Bumbu', 1.0],
                ],
            ],
            [
                'nama_resep' => 'Bubur Kacang Hijau Susu',
                'kategori_gizi' => 'tinggi zat besi',
                'langkah' => [
                    'Rendam kacang hijau selama 2 jam agar lebih cepat empuk saat dimasak.',
                    'Rebus kacang hijau dengan air secukupnya hingga empuk (±20 menit).',
                    'Tambahkan beras yang sudah dicuci, masak hingga menjadi bubur kental.',
                    'Masukkan daun pandan untuk aroma, aduk sesekali agar tidak gosong.',
                    'Tuang susu cair, aduk rata dan masak dengan api kecil selama 5 menit.',
                    'Angkat daun pandan, sajikan bubur dalam mangkuk.',
                ],
                'bahans' => [
                    ['Kacang hijau', 'Kacang-kacangan', 100.0],
                    ['Beras', 'Serealia', 60.0],
                    ['Susu cair', 'Susu', 150.0],
                    ['Gula merah', 'Gula', 15.0],
                    ['Daun pandan', 'Bumbu', 2.0],
                ],
            ],
            [
                'nama_resep' => 'Pancake Pisang Oatmeal',
                'kategori_gizi' => 'tinggi karbohidrat',
                'langkah' => [
                    'Haluskan pisang matang dengan garpu hingga lembut seperti pasta.',
                    'Campurkan oatmeal, telur, dan susu ke dalam pisang halus. Aduk rata.',
                    'Diamkan adonan selama 5 menit agar oatmeal menyerap cairan.',
                    'Panaskan wajan anti lengket dengan api kecil, olesi sedikit minyak.',
                    'Tuang 1 sendok makan adonan, masak 2-3 menit hingga muncul gelembung.',
                    'Balik pancake, masak 1-2 menit lagi. Sajikan dengan drizzle madu.',
                ],
                'bahans' => [
                    ['Pisang', 'Buah', 80.0],
                    ['Oatmeal', 'Serealia', 40.0],
                    ['Telur', 'Protein Hewani', 50.0],
                    ['Susu', 'Susu', 50.0],
                    ['Madu', 'Gula', 5.0],
                ],
            ],
        ];
    }
}
