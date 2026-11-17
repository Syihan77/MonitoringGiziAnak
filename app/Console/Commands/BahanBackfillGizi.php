<?php

namespace App\Console\Commands;

use App\Models\BahanMakanan;
use App\Support\BahanResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BahanBackfillGizi extends Command
{
    protected $signature = 'bahan:backfill-gizi
        {--apply : Terapkan hasil backfill; tanpa opsi ini hanya dry-run}';

    protected $description = 'Isi nilai gizi baris placeholder lama dari TKPI melalui alias eksplisit berkepercayaan tinggi';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $placeholder = BahanResolver::KOLOM_GIZI;

        $rows = BahanMakanan::query()
            ->where('sumber_rujukan', 'like', BahanResolver::LABEL_PLACEHOLDER.'%')
            ->where(function ($q) use ($placeholder) {
                foreach ($placeholder as $kolom) {
                    $q->whereNull($kolom);
                }
            })
            ->orderBy('nama_bahan')
            ->get();

        $pasangan = [];
        $tanpaPasangan = [];

        foreach ($rows as $bahan) {
            $target = BahanResolver::targetBackfill($bahan);

            if ($target === null) {
                $tanpaPasangan[] = $bahan->nama_bahan;

                continue;
            }

            $pasangan[] = ['placeholder' => $bahan, 'target' => $target];
        }

        if ($pasangan !== []) {
            $this->table(
                ['Placeholder', 'TKPI', 'Kode', 'E', 'P', 'L', 'KH', 'Fe', 'Zn'],
                array_map(fn (array $item) => [
                    $item['placeholder']->nama_bahan,
                    $item['target']->nama_bahan,
                    $this->kodeTkpi($item['target']),
                    $this->nilai($item['target']->energi_per100g),
                    $this->nilai($item['target']->protein_per100g),
                    $this->nilai($item['target']->lemak_per100g),
                    $this->nilai($item['target']->karbohidrat_per100g),
                    $this->nilai($item['target']->zat_besi_per100g),
                    $this->nilai($item['target']->zinc_per100g),
                ], $pasangan),
            );
        }

        if ($tanpaPasangan !== []) {
            $this->warn('Placeholder tanpa alias eksplisit (tetap NULL): '.implode(', ', $tanpaPasangan));
        }

        if ($apply && $pasangan !== []) {
            DB::transaction(function () use ($pasangan) {
                foreach ($pasangan as $item) {
                    $placeholder = $item['placeholder'];
                    $target = $item['target'];

                    $placeholder->update([
                        ...collect(BahanResolver::KOLOM_GIZI)
                            ->mapWithKeys(fn (string $kolom) => [$kolom => $target->{$kolom}])
                            ->all(),
                        'sumber_rujukan' => $this->catatanAsal($placeholder, $target),
                    ]);
                }
            });
        }

        $this->info(sprintf(
            '%s: %d placeholder NULL, %d memiliki pasangan eksplisit, %d tanpa pasangan.',
            $apply ? 'Backfill diterapkan' : 'Dry-run',
            $rows->count(),
            count($pasangan),
            count($tanpaPasangan),
        ));

        return self::SUCCESS;
    }

    private function kodeTkpi(BahanMakanan $bahan): string
    {
        if (preg_match('/TKPI\s+20\d{2}\s+([A-Z0-9]+)/i', (string) $bahan->sumber_rujukan, $cocok) === 1) {
            return $cocok[1];
        }

        return (string) $bahan->sumber_rujukan;
    }

    private function catatanAsal(BahanMakanan $placeholder, BahanMakanan $target): string
    {
        $catatan = sprintf(
            '%s; TKPI 2020 %s; alias %s',
            BahanResolver::LABEL_PLACEHOLDER,
            $this->kodeTkpi($target),
            mb_substr($placeholder->nama_bahan, 0, 50),
        );

        return mb_substr($catatan, 0, 100);
    }

    private function nilai(mixed $nilai): string
    {
        return $nilai === null ? 'NULL' : (string) $nilai;
    }
}
