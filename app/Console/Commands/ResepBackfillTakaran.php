<?php

namespace App\Console\Commands;

use App\Models\Resep;
use App\Models\ResepBahan;
use App\Support\TakaranCatatan;
use App\Support\TakaranConverter;
use Illuminate\Console\Command;

class ResepBackfillTakaran extends Command
{
    protected $signature = 'resep:backfill-takaran {--dry-run : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Perbaiki satuan takaran resep dataset yang masih null secara idempoten';

    public function handle(): int
    {
        $path = base_path(implode(DIRECTORY_SEPARATOR, [
            'database', 'seeders', 'data', 'dataset_resep_gizi_terstandarisasi_berlabelusia_tkpi.csv',
        ]));

        if (! is_file($path)) {
            $this->error("Berkas dataset tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Gagal membaca berkas dataset: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false) {
            fclose($handle);
            $this->error('Header CSV tidak dapat dibaca.');

            return self::FAILURE;
        }

        $header = array_map(fn ($nama) => trim((string) $nama), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $dryRun = (bool) $this->option('dry-run');
        $perubahan = 0;
        $diproses = [];
        $kunciDataset = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row = array_pad($row, count($header), null);
            $data = array_combine($header, $row);

            if ($data === false) {
                continue;
            }

            $idResep = trim((string) ($data['id_resep'] ?? ''));
            if ($idResep === '') {
                continue;
            }

            $resep = Resep::where('id_resep', $idResep)->first();
            if ($resep === null) {
                continue;
            }

            $semua = ResepBahan::with('bahan')->where('resep_id', $resep->id)->get();
            $null = $semua->whereNull('takaran_satuan');

            if ($null->isEmpty()) {
                continue;
            }

            foreach (explode(';', (string) ($data['bahan_dan_bumbu'] ?? '')) as $baris) {
                $baris = trim($baris);
                if ($baris === '') {
                    continue;
                }

                $parsed = TakaranConverter::parse($baris);
                $nama = TakaranConverter::normalisasi($parsed['nama']);
                $kunci = $this->kunci($parsed['nama']);
                $pelengkap = TakaranConverter::pelengkap($parsed['nama']);
                $kunciDataset[$resep->id][$kunci] = true;

                if ($parsed['satuan'] === null && $parsed['gram'] === null && $pelengkap) {
                    continue;
                }

                $satuan = $parsed['satuan'] ?? 'secukupnya';
                $catatan = TakaranCatatan::untuk(
                    $idResep,
                    $nama,
                    $pelengkap,
                    $satuan,
                    $parsed['gram'],
                );

                $match = $null->first(function (ResepBahan $rb) use ($nama, $kunci): bool {
                    $namaBahan = TakaranConverter::normalisasi($rb->bahan->nama_bahan);
                    $kunciBahan = $this->kunci($rb->bahan->nama_bahan);

                    return $namaBahan === $nama
                        || str_contains($namaBahan, $nama)
                        || str_contains($nama, $namaBahan)
                        || $kunciBahan === $kunci;
                });

                if ($match === null) {
                    continue;
                }

                $this->tulis($match, $parsed['jumlah'], $satuan, $parsed['gram'], $catatan, $dryRun);

                $diproses[$match->id] = true;
                $null = $null->reject(fn (ResepBahan $rb) => $rb->id === $match->id);
                $perubahan++;
            }
        }

        fclose($handle);

        foreach (ResepBahan::with('bahan')->whereNull('takaran_satuan')->get() as $rb) {
            if (isset($diproses[$rb->id]) || ! TakaranConverter::pelengkap($rb->bahan->nama_bahan)) {
                continue;
            }

            $this->tulis($rb, null, 'secukupnya', null, 'Pelengkap tanpa takaran', $dryRun);
            $perubahan++;
        }

        $sisaDataset = [];
        foreach (ResepBahan::with('bahan')->whereNull('takaran_satuan')->get() as $rb) {
            $kunci = $this->kunci($rb->bahan->nama_bahan);

            if (isset($kunciDataset[$rb->resep_id][$kunci])) {
                $sisaDataset[] = $rb;

                continue;
            }

            $this->warn(
                "{$rb->resep->id_resep} {$rb->bahan->nama_bahan}: tidak ditemukan di dataset; set secukupnya."
            );

            if (! $dryRun) {
                $rb->update([
                    'takaran_jumlah' => null,
                    'takaran_satuan' => 'secukupnya',
                    'takaran_gram_ekuivalen' => null,
                    'catatan_bahan' => 'Tidak ditemukan di dataset; perlu verifikasi',
                ]);
            }
        }

        if (! $dryRun && $sisaDataset !== []) {
            $this->error('Masih ada takaran_satuan null untuk bahan dataset:');
            foreach ($sisaDataset as $rb) {
                $this->line(" - {$rb->resep->id_resep}: {$rb->bahan->nama_bahan}");
            }

            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Dry-run' : 'Backfill')." selesai. {$perubahan} baris diproses.");

        return self::SUCCESS;
    }

    private function kunci(string $nama): string
    {
        $nama = preg_replace('/\([^)]*\)/u', ' ', $nama) ?? $nama;
        $bagian = preg_split('/[,;:]/u', $nama, 2);
        $nama = $bagian[0] ?? $nama;

        return TakaranConverter::normalisasi($nama);
    }

    private function tulis(
        ResepBahan $rb,
        ?float $jumlah,
        string $satuan,
        ?float $gram,
        ?string $catatan,
        bool $dryRun,
    ): void {
        $this->line(sprintf(
            '%s %s: %s %s (%s g) -> %s',
            $rb->resep->id_resep,
            $rb->bahan->nama_bahan,
            $jumlah === null ? '-' : rtrim(rtrim(number_format($jumlah, 2, '.', ''), '0'), '.'),
            $satuan,
            $gram === null ? '-' : rtrim(rtrim(number_format($gram, 2, '.', ''), '0'), '.'),
            $catatan ?? '-',
        ));

        if ($dryRun) {
            return;
        }

        $rb->update([
            'takaran_jumlah' => $jumlah,
            'takaran_satuan' => $satuan,
            'takaran_gram_ekuivalen' => $gram,
            'catatan_bahan' => $catatan,
        ]);
    }
}
