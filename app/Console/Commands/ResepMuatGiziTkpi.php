<?php

namespace App\Console\Commands;

use App\Models\Resep;
use App\Support\DatasetGiziTkpi;
use Illuminate\Console\Command;

class ResepMuatGiziTkpi extends Command
{
    protected $signature = 'resep:muat-gizi-tkpi
        {--dry-run : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Muat nilai gizi resep dari dataset TKPI dan turunkan status bila empat makro tidak lengkap';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = DatasetGiziTkpi::rows();
        $reseps = Resep::query()
            ->whereIn('id_resep', array_column($rows, 'id_resep'))
            ->get()
            ->keyBy('id_resep');

        $jumlahResep = 0;
        $jumlahSel = 0;
        $jumlahCatatan = 0;
        $jumlahStatus = 0;
        $tidakDitemukan = [];
        $rincian = [];

        foreach ($rows as $row) {
            $idResep = $row['id_resep'];
            $resep = $reseps->get($idResep);

            if ($resep === null) {
                $tidakDitemukan[] = $idResep;

                continue;
            }

            $nilaiBaru = DatasetGiziTkpi::gizi($row);
            $atribut = [];
            $kolomBerubah = [];

            foreach (DatasetGiziTkpi::KOLOM as $kolomDb) {
                if ($this->berbeda($resep->{$kolomDb}, $nilaiBaru[$kolomDb])) {
                    $atribut[$kolomDb] = $nilaiBaru[$kolomDb];
                    $kolomBerubah[] = $kolomDb;
                }
            }

            $catatanBaru = DatasetGiziTkpi::catatan($resep->catatan_resep);

            if ($catatanBaru !== $resep->catatan_resep) {
                $atribut['catatan_resep'] = $catatanBaru;
            }

            $makroLengkap = collect(Resep::MAKRO_WAJIB)
                ->every(fn (string $kolom) => $nilaiBaru[$kolom] !== null);

            if (! $makroLengkap) {
                if ($resep->status_verifikasi_data !== Resep::VERIFIKASI_PERLU_VERIFIKASI) {
                    $atribut['status_verifikasi_data'] = Resep::VERIFIKASI_PERLU_VERIFIKASI;
                    $jumlahStatus++;
                }

                $catatan = $atribut['catatan_resep'] ?? $resep->catatan_resep;

                if (! str_contains((string) $catatan, Resep::CATATAN_MAKRO_TIDAK_LENGKAP)) {
                    $atribut['catatan_resep'] = Resep::tambahkanAlasanMakro($catatan);
                }
            }

            if ($atribut === []) {
                continue;
            }

            $catatanBerubah = ($atribut['catatan_resep'] ?? $resep->catatan_resep) !== $resep->catatan_resep;

            $jumlahResep++;
            $jumlahSel += count($kolomBerubah);
            if ($catatanBerubah) {
                $jumlahCatatan++;
            }

            $rincian[] = [
                $idResep,
                count($kolomBerubah),
                $catatanBerubah ? 'ya' : 'tidak',
            ];

            if (! $dryRun) {
                $resep->update($atribut);
            }
        }

        $this->tampilkanRincian($rincian);

        if ($tidakDitemukan !== []) {
            $this->warn('ID resep tidak ditemukan: '.implode(', ', $tidakDitemukan));
        }

        if ($jumlahStatus > 0) {
            $this->warn("Status diturunkan ke PERLU_VERIFIKASI: {$jumlahStatus} resep.");
        }

        $this->info(sprintf(
            '%s: %d resep, %d sel gizi, %d catatan berubah.',
            $dryRun ? 'Dry-run' : 'Muat gizi TKPI',
            $jumlahResep,
            $jumlahSel,
            $jumlahCatatan,
        ));

        return $tidakDitemukan === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: string}>  $rincian
     */
    private function tampilkanRincian(array $rincian): void
    {
        if ($rincian === []) {
            return;
        }

        $this->table(
            ['ID resep', 'Sel gizi berubah', 'Catatan berubah'],
            array_slice($rincian, 0, 20),
        );

        if (count($rincian) > 20) {
            $this->line('... dan '.(count($rincian) - 20).' resep lainnya.');
        }
    }

    private function berbeda(mixed $lama, ?float $baru): bool
    {
        if ($baru === null) {
            return $lama !== null;
        }

        if ($lama === null) {
            return true;
        }

        return abs(round($baru, 2) - round((float) $lama, 2)) >= 0.001;
    }
}
