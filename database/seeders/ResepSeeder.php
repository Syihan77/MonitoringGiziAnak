<?php

namespace Database\Seeders;

use App\Models\BahanMakanan;
use App\Models\Konsumsi;
use App\Models\RekomendasiLog;
use App\Models\Resep;
use App\Support\TakaranConverter;
use Illuminate\Database\Seeder;
use RuntimeException;

class ResepSeeder extends Seeder
{
    private const KOLOM = [
        'id_resep',
        'nama_menu',
        'porsi',
        'waktu_memasak',
        'bahan_dan_bumbu',
        'cara_membuat',
        'energi_kcal_per_porsi',
        'karbohidrat_g_per_porsi',
        'protein_g_per_porsi',
        'lemak_g_per_porsi',
        'zat_besi_mg_per_porsi',
        'vitamin_c_mg_per_porsi',
        'kalsium_mg_per_porsi',
        'zinc_mg_per_porsi',
        'vitamin_a_RE_per_porsi',
        'serat_g_per_porsi',
        'usia_minimal_bulan',
        'usia_minimal_bulan_jika_dimodifikasi',
        'jenis_menu',
        'tekstur',
        'alasan_usia_resep_asli',
        'modifikasi_untuk_turunkan_usia',
        'alergen',
        'ada_garam_atau_gula',
        'catatan',
        'keyakinan_label',
    ];

    private const ALIAS_BAHAN = [
        'nasi putih' => 'nasi',
        'minyak goreng' => 'minyak kelapa',
        'minyak sayur' => 'minyak kelapa',
        'gula pasir' => 'gula pasir',
        'gula merah' => 'gula merah',
        'telur ayam' => 'telur ayam',
        'telur puyuh' => 'telur puyuh',
        'daun bawang' => 'daun bawang',
        'bawang bombay' => 'bawang bombay',
        'bawang merah' => 'bawang merah',
        'bawang putih' => 'bawang putih',
        'susu sapi' => 'susu sapi',
        'santan' => 'santan',
        'kacang hijau' => 'kacang hijau',
    ];

    public function run(): void
    {
        foreach ($this->recipes() as $recipe) {
            $this->simpanResep($recipe);
        }

        $this->bersihkanMockLama();
    }

    private function sourcePath(): string
    {
        return base_path(implode(DIRECTORY_SEPARATOR, [
            'database', 'seeders', 'data', 'dataset_resep_gizi_terstandarisasi_berlabelusia.csv',
        ]));
    }

    private function recipes(): array
    {
        $path = $this->sourcePath();
        if (! is_file($path)) {
            throw new RuntimeException("Berkas dataset resep tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Gagal membaca berkas dataset: {$path}");
        }

        $hasil = [];
        $nomor = 0;
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $nomor++;
            if ($nomor === 1) {
                continue;
            }

            if (count($row) < 3) {
                continue;
            }

            $hasil[] = $this->bacaBaris($row);
        }

        fclose($handle);

        return $hasil;
    }

    private function bacaBaris(array $row): array
    {
        $recipe = [];
        foreach (self::KOLOM as $index => $field) {
            $recipe[$field] = trim((string) ($row[$index] ?? ''));
        }

        return $recipe;
    }

    private function simpanResep(array $recipe): void
    {
        if ($recipe['id_resep'] === '' || $recipe['nama_menu'] === '') {
            return;
        }

        $resep = Resep::updateOrCreate(
            ['id_resep' => $recipe['id_resep']],
            [
                'nama_resep' => $recipe['nama_menu'],
                'cara_memasak' => $this->caraMemasak($recipe['cara_membuat']),
                'kategori_gizi' => $recipe['jenis_menu'] ?: null,
                'estimasi_biaya' => null,
                'porsi_saji' => null,
                'jumlah_porsi_hasil' => $this->angkaPorsi($recipe['porsi']),
                'energi_per_porsi' => $this->desimal($recipe['energi_kcal_per_porsi']),
                'karbohidrat_per_porsi' => $this->desimal($recipe['karbohidrat_g_per_porsi']),
                'protein_per_porsi' => $this->desimal($recipe['protein_g_per_porsi']),
                'lemak_per_porsi' => $this->desimal($recipe['lemak_g_per_porsi']),
                'zat_besi_per_porsi' => $this->desimal($recipe['zat_besi_mg_per_porsi']),
                'vitamin_c_per_porsi' => $this->desimal($recipe['vitamin_c_mg_per_porsi']),
                'kalsium_per_porsi' => $this->desimal($recipe['kalsium_mg_per_porsi']),
                'zinc_per_porsi' => $this->desimal($recipe['zinc_mg_per_porsi']),
                'vitamin_a_per_porsi' => $this->desimal($recipe['vitamin_a_RE_per_porsi']),
                'serat_per_porsi' => $this->desimal($recipe['serat_g_per_porsi']),
                'usia_min_bulan' => $this->bulat($recipe['usia_minimal_bulan']),
                'usia_maks_bulan' => null,
                'tekstur_sajian' => $recipe['tekstur'] ?: null,
                'sumber_resep' => 'Dataset Resep Gizi Terstandarisasi',
                'status_verifikasi_data' => Resep::VERIFIKASI_PERLU_VERIFIKASI,
                'tingkat_kesulitan' => $this->tingkatKesulitan($recipe['waktu_memasak']),
                'id_resep' => $recipe['id_resep'],
                'usia_minimal_bulan_jika_dimodifikasi' => $this->bulat($recipe['usia_minimal_bulan_jika_dimodifikasi']),
                'modifikasi_untuk_turunkan_usia' => $this->teksAtauNull($recipe['modifikasi_untuk_turunkan_usia']),
                'keyakinan_label' => $recipe['keyakinan_label'] ?: null,
                'jenis_menu' => $recipe['jenis_menu'] ?: null,
                'alergen' => $this->alergen($recipe['alergen']),
                'catatan_resep' => $this->teksAtauNull($recipe['catatan']),
            ]
        );

        $lengkap = $this->simpanBahan($resep, $recipe['bahan_dan_bumbu']);

        $status = $lengkap && $recipe['keyakinan_label'] !== 'rendah'
            ? Resep::VERIFIKASI_DATA_LENGKAP
            : Resep::VERIFIKASI_PERLU_VERIFIKASI;

        $resep->update(['status_verifikasi_data' => $status]);
    }

    private function simpanBahan(Resep $resep, string $bahanTeks): bool
    {
        $resep->bahans()->delete();

        if (trim($bahanTeks) === '') {
            return false;
        }

        $lengkap = true;

        foreach (explode(';', $bahanTeks) as $baris) {
            $baris = trim($baris);
            if ($baris === '') {
                continue;
            }

            $parsed = TakaranConverter::parse($baris);
            $nama = $this->namaDasar($parsed['nama']);

            if ($nama === '' || preg_match('/\p{L}/u', $nama) !== 1 || preg_match('/^[\d½¼¾⅓⅔⅛⅜⅝⅞]/u', $nama) === 1) {
                $lengkap = false;
                continue;
            }

            $pelengkap = TakaranConverter::pelengkap($nama);
            if (! $pelengkap && $parsed['gram'] === null) {
                $lengkap = false;
            }

            if ($parsed['gram'] !== null && $parsed['gram'] <= 0) {
                $lengkap = false;
            }

            $bahan = $this->cariBahan($nama);
            $resep->bahans()->create([
                'bahan_id' => $bahan->id,
                'takaran_gram' => $parsed['gram'],
                'takaran_jumlah' => $parsed['jumlah'],
                'takaran_satuan' => $parsed['satuan'],
                'takaran_gram_ekuivalen' => $parsed['gram'],
                'catatan_bahan' => $pelengkap && $parsed['gram'] === null ? 'Pelengkap tanpa takaran' : null,
            ]);
        }

        return $lengkap && $resep->bahans()->exists();
    }

    private function cariBahan(string $nama): BahanMakanan
    {
        $normal = TakaranConverter::normalisasi($nama);
        $alias = self::ALIAS_BAHAN[$normal] ?? $normal;

        $bahan = BahanMakanan::whereRaw('LOWER(nama_bahan) = ?', [$alias])->first()
            ?? BahanMakanan::whereRaw('LOWER(nama_bahan) LIKE ?', ['%'.$alias.'%'])
                ->orderByRaw('LENGTH(nama_bahan)')
                ->first();

        if ($bahan !== null) {
            return $bahan;
        }

        return BahanMakanan::firstOrCreate(
            ['nama_bahan' => $nama],
            [
                'kelompok_gizi' => 'Dataset',
                'sumber_rujukan' => 'Dataset resep berlabel',
            ]
        );
    }

    private function namaDasar(string $nama): string
    {
        $nama = preg_replace('/\([^)]*\)/u', ' ', $nama) ?? $nama;
        $nama = explode(',', $nama)[0];
        $nama = explode('/', $nama)[0];

        return trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama);
    }

    private function alergen(string $nilai): array
    {
        if ($nilai === '' || str_contains(mb_strtolower($nilai), 'tidak ada')) {
            return [];
        }

        $hasil = [];

        foreach (explode(',', $nilai) as $bagian) {
            $bagian = mb_strtolower(trim($bagian));
            if ($bagian === '') {
                continue;
            }

            $hasil[] = match (true) {
                str_contains($bagian, 'telur') => 'telur',
                str_contains($bagian, 'udang'), str_contains($bagian, 'ebi') => 'udang/ebi',
                str_contains($bagian, 'ikan') => 'ikan',
                str_contains($bagian, 'susu') => 'susu sapi/olahannya',
                str_contains($bagian, 'kedelai') => 'kedelai',
                str_contains($bagian, 'gluten') => 'gluten',
                str_contains($bagian, 'wijen') => 'wijen',
                str_contains($bagian, 'kacang') => 'kacang-kacangan',
                default => $bagian,
            };
        }

        return array_values(array_unique($hasil));
    }

    private function bersihkanMockLama(): void
    {
        $mock = Resep::whereIn('nama_resep', [
            'Omelet Bayam & Roti Gandum',
            'Pancake Pisang Oatmeal',
        ])->get();

        foreach ($mock as $resep) {
            $dipakai = Konsumsi::where('resep_id', $resep->id)->exists()
                || RekomendasiLog::where('resep_id', $resep->id)->exists();

            if ($dipakai) {
                $resep->update([
                    'status_verifikasi_data' => Resep::VERIFIKASI_PERLU_VERIFIKASI,
                    'sumber_resep' => 'Arsip mock lama',
                ]);
                continue;
            }

            $resep->bahans()->delete();
            $resep->delete();
        }

        $arsip = Resep::where('nama_resep', 'Bubur Kacang Hijau Susu')->first();
        if ($arsip !== null) {
            $arsip->update([
                'status_verifikasi_data' => Resep::VERIFIKASI_PERLU_VERIFIKASI,
                'sumber_resep' => 'Arsip mock lama',
            ]);
        }
    }

    private function caraMemasak(string $cara): string
    {
        $langkah = preg_split('/\s*\|\s*/u', trim($cara)) ?: [];

        return trim(implode("\n", array_filter(array_map('trim', $langkah))));
    }

    private function angkaPorsi(string $value): int
    {
        if (preg_match('/\d+/', $value, $matches) === 1) {
            return max(1, (int) $matches[0]);
        }

        return 1;
    }

    private function bulat(string $value): ?int
    {
        if (preg_match('/\d+/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function desimal(string $value): ?float
    {
        if (preg_match('/\d+(?:[.,]\d+)?/', $value, $matches) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }

    private function teksAtauNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        return $value;
    }

    private function tingkatKesulitan(string $waktu): string
    {
        $menit = $this->bulat($waktu) ?? 0;

        return match (true) {
            $menit <= 30 => 'Mudah',
            $menit <= 60 => 'Sedang',
            default => 'Sulit',
        };
    }
}
