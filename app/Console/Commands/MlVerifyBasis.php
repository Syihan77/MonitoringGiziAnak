<?php

namespace App\Console\Commands;

use App\Models\Resep;
use Illuminate\Console\Command;

class MlVerifyBasis extends Command
{
    private const TOLERANSI = 0.011;

    /**
     * @var array<string, string>
     */
    private const KOLOM = [
        'energi' => 'energi_per_porsi',
        'karbohidrat' => 'karbohidrat_per_porsi',
        'protein' => 'protein_per_porsi',
        'lemak' => 'lemak_per_porsi',
        'zat_besi' => 'zat_besi_per_porsi',
        'vitamin_c' => 'vitamin_c_per_porsi',
        'kalsium' => 'kalsium_per_porsi',
        'zinc' => 'zinc_per_porsi',
        'vitamin_a' => 'vitamin_a_per_porsi',
        'serat' => 'serat_per_porsi',
    ];

    protected $signature = 'ml:verify-basis';

    protected $description = 'Bandingkan basis_resep.csv ML2 (read-only) dengan tabel reseps';

    public function handle(): int
    {
        $path = (string) config('services.ml.ml2.basis_csv');

        if ($path === '' || ! is_file($path)) {
            $this->error("Berkas basis ML2 tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Berkas basis ML2 tidak dapat dibaca: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle, 0, ',', '"', '');

        if ($header === false) {
            fclose($handle);
            $this->error('Header CSV basis ML2 tidak dapat dibaca.');

            return self::FAILURE;
        }

        $header = array_map(fn ($nama) => trim((string) $nama), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $wajib = ['id_resep', ...array_keys(self::KOLOM)];
        $kurang = array_values(array_diff($wajib, $header));

        if ($kurang !== []) {
            fclose($handle);
            $this->error('Kolom wajib tidak ditemukan: '.implode(', ', $kurang));

            return self::FAILURE;
        }

        $reseps = Resep::query()
            ->whereNotNull('id_resep')
            ->get()
            ->keyBy('id_resep');
        $idCsv = [];
        $jumlahBaris = 0;
        $jumlahCocok = 0;
        $selisih = [];
        $selisihPerZat = array_fill_keys(array_keys(self::KOLOM), 0);
        $tidakDitemukan = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row = array_pad($row, count($header), null);
            $data = array_combine($header, $row);

            if ($data === false) {
                continue;
            }

            $id = trim((string) ($data['id_resep'] ?? ''));

            if ($id === '') {
                continue;
            }

            $jumlahBaris++;
            $idCsv[$id] = true;
            $resep = $reseps->get($id);

            if ($resep === null) {
                $tidakDitemukan[] = $id;

                continue;
            }

            $beda = [];
            foreach (self::KOLOM as $kolomCsv => $kolomDb) {
                $nilaiCsv = $this->angka($data[$kolomCsv] ?? null);
                $nilaiDb = $this->angka($resep->{$kolomDb});

                if ($nilaiCsv === null && $nilaiDb === null) {
                    continue;
                }

                if ($nilaiCsv === null || $nilaiDb === null
                    || abs($nilaiCsv - $nilaiDb) > self::TOLERANSI) {
                    $selisihPerZat[$kolomCsv]++;
                    $beda[] = sprintf(
                        '%s: CSV %s vs DB %s',
                        $kolomCsv,
                        $nilaiCsv === null ? 'NULL' : $nilaiCsv,
                        $nilaiDb === null ? 'NULL' : $nilaiDb,
                    );
                }
            }

            if ($beda === []) {
                $jumlahCocok++;
            } else {
                $selisih[] = ['id_resep' => $id, 'beda' => implode('; ', $beda)];
            }
        }

        fclose($handle);

        $idTanpaCsv = $reseps->keys()
            ->reject(fn ($id) => isset($idCsv[$id]))
            ->values()
            ->all();

        $this->info("Basis ML2: {$path}");
        $this->line(sprintf(
            'Baris CSV: %d | cocok: %d | selisih: %d | tidak ada di DB: %d | DB tanpa CSV: %d',
            $jumlahBaris,
            $jumlahCocok,
            count($selisih),
            count($tidakDitemukan),
            count($idTanpaCsv),
        ));

        $ringkasanPerZat = [];
        foreach ($selisihPerZat as $zat => $jumlah) {
            $ringkasanPerZat[] = "{$zat}={$jumlah}";
        }
        $this->line('Selisih per zat: '.implode(', ', $ringkasanPerZat));

        if ($tidakDitemukan !== []) {
            $this->warn('ID di CSV tetapi tidak ada di tabel reseps: '.implode(', ', $tidakDitemukan));
        }

        if ($idTanpaCsv !== []) {
            $this->warn('ID di tabel reseps tetapi tidak ada di CSV: '.implode(', ', $idTanpaCsv));
        }

        if ($selisih !== []) {
            $this->table(['ID resep', 'Selisih'], array_slice($selisih, 0, 20));

            if (count($selisih) > 20) {
                $this->warn('Selisih lainnya tidak ditampilkan (maksimal 20 baris).');
            }
        }

        if ($jumlahBaris === 0) {
            $this->error('CSV basis ML2 tidak berisi baris data.');

            return self::FAILURE;
        }

        return $selisih === [] && $tidakDitemukan === [] && $idTanpaCsv === []
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function angka(mixed $nilai): ?float
    {
        if ($nilai === null) {
            return null;
        }

        $teks = trim(str_replace(' ', '', (string) $nilai));

        if ($teks === '' || in_array(strtolower($teks), ['nan', 'null', '-', 'n/a', 'na'], true)) {
            return null;
        }

        return (float) str_replace(',', '.', $teks);
    }
}
