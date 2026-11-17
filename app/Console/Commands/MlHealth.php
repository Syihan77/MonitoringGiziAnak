<?php

namespace App\Console\Commands;

use App\Services\MlClient;
use Illuminate\Console\Command;

class MlHealth extends Command
{
    protected $signature = 'ml:health';

    protected $description = 'Periksa kesiapan layanan ML dan kedua modulnya';

    public function handle(MlClient $ml): int
    {
        $hasil = $ml->health();
        $data = is_array($hasil['data']) ? $hasil['data'] : [];
        $ml1 = is_array($data['modules']['ml1'] ?? null) ? $data['modules']['ml1'] : [];
        $ml2 = is_array($data['modules']['ml2'] ?? null) ? $data['modules']['ml2'] : [];

        $this->line('Service: '.((bool) ($data['service'] ?? false) ? 'running' : 'tidak tersedia'));
        $this->line('ML1: '.((bool) ($ml1['ready'] ?? false) ? 'siap' : 'tidak siap'));
        $this->line('ML2: '.((bool) ($ml2['ready'] ?? false) ? 'siap' : 'tidak siap'));

        foreach ($ml2['catatan_kompatibilitas'] ?? [] as $catatan) {
            $this->warn('Catatan kompatibilitas ML2: '.$catatan);
        }

        if (! $hasil['ok']) {
            $this->error('ML health gagal: '.($hasil['alasan'] ?? 'tidak siap'));

            return self::FAILURE;
        }

        $this->info('ML health: siap.');

        return self::SUCCESS;
    }
}
