<?php

namespace App\Console\Commands;

use App\Models\BahanMakanan;
use App\Models\Resep;
use App\Support\BahanResolver;
use App\Support\DatasetGiziTkpi;
use Database\Seeders\ResepSeeder;
use Illuminate\Console\Command;

class ResepPerbaikiBahan extends Command
{
    protected $signature = 'resep:perbaiki-bahan
        {--dry-run : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Perbaiki pemetaan resep_bahans dataset secara idempoten tanpa mengubah CSV atau paket ML';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $seeder = app(ResepSeeder::class);
        $diperiksa = 0;
        $berubah = 0;
        $barisBerubah = 0;
        $tidakDitemukan = [];
        $rincian = [];

        foreach (DatasetGiziTkpi::rows() as $recipe) {
            $resep = Resep::where('id_resep', $recipe['id_resep'])->first();

            if ($resep === null) {
                $tidakDitemukan[] = $recipe['id_resep'];

                continue;
            }

            $diperiksa++;
            $rencana = $seeder->rencanaBahan(
                $recipe['bahan_dan_bumbu'],
                false,
                $recipe['id_resep'],
            );
            $sekarang = $resep->bahans()->with('bahan')->get();
            $signatureSekarang = $this->signature($sekarang->map(fn ($rb) => [
                'bahan_id' => $rb->bahan_id,
                'jumlah' => $rb->takaran_jumlah,
                'satuan' => $rb->takaran_satuan,
                'gram' => $rb->takaran_gram_ekuivalen,
                'catatan' => $rb->catatan_bahan,
            ])->all());
            $signatureTarget = $this->signature(collect($rencana['baris'])->map(fn ($baris) => [
                'bahan_id' => $baris['bahan']?->id,
                'jumlah' => $baris['jumlah'],
                'satuan' => $baris['satuan'],
                'gram' => $baris['gram'],
                'catatan' => $baris['catatan'],
            ])->all());

            if ($signatureSekarang === $signatureTarget) {
                continue;
            }

            $berubah++;
            $barisBerubah += max(count($signatureSekarang), count($signatureTarget));

            foreach ($rencana['baris'] as $baris) {
                $rincian[] = [
                    $recipe['id_resep'],
                    $baris['nama_dataset'],
                    $baris['bahan']?->nama_bahan ?? '(belum ada)',
                    $this->angka($baris['gram']),
                    $this->status($baris['bahan']),
                ];
            }

            if (! $dryRun) {
                $seeder->sinkronkanBahan($resep, $recipe['bahan_dan_bumbu']);
            }
        }

        if ($rincian !== []) {
            $this->table(
                ['ID resep', 'Bahan dataset', 'Terpetakan', 'Gram', 'Status'],
                $rincian,
            );
        }

        if ($tidakDitemukan !== []) {
            $this->warn('ID resep tidak ditemukan: '.implode(', ', $tidakDitemukan));
        }

        $this->info(sprintf(
            '%s: %d resep diperiksa, %d resep perlu diperbaiki, %d baris bahan ditampilkan.',
            $dryRun ? 'Dry-run' : 'Perbaikan',
            $diperiksa,
            $berubah,
            $barisBerubah,
        ));

        return $tidakDitemukan === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{bahan_id: string|null, jumlah: float|null, satuan: string|null, gram: float|null, catatan: string|null}>  $baris
     * @return list<string>
     */
    private function signature(array $baris): array
    {
        $hasil = array_map(fn (array $row) => implode('|', [
            $row['bahan_id'] ?? '',
            $this->angka($row['jumlah']),
            $row['satuan'] ?? '',
            $this->angka($row['gram']),
            $row['catatan'] ?? '',
        ]), $baris);

        sort($hasil);

        return $hasil;
    }

    private function status(?BahanMakanan $bahan): string
    {
        if ($bahan === null) {
            return 'MISSING';
        }

        return BahanResolver::placeholder($bahan) || BahanResolver::kosongSemua($bahan)
            ? 'NULL'
            : 'OK';
    }

    private function angka(mixed $nilai): string
    {
        return $nilai === null ? '' : number_format((float) $nilai, 2, '.', '');
    }
}
