<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MlHealthTest extends TestCase
{
    private function body(bool $ml1 = true, bool $ml2 = true, array $catatan = []): array
    {
        return [
            'status' => $ml1 && $ml2 ? 'ok' : 'degraded',
            'service' => 'running',
            'modules' => [
                'ml1' => ['ready' => $ml1],
                'ml2' => ['ready' => $ml2, 'catatan_kompatibilitas' => $catatan],
            ],
        ];
    }

    public function test_health_sukses_dan_catatan_kompatibilitas_hanya_peringatan(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/health' => Http::response(
                $this->body(catatan: ['InconsistentVersionWarning: versi scikit-learn berbeda']),
                200,
            ),
        ]);

        $this->artisan('ml:health')
            ->expectsOutputToContain('ML1: siap')
            ->expectsOutputToContain('ML2: siap')
            ->expectsOutputToContain('Catatan kompatibilitas ML2: InconsistentVersionWarning')
            ->expectsOutputToContain('ML health: siap.')
            ->assertSuccessful();
    }

    public function test_health_gagal_bila_salah_satu_modul_tidak_siap(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/health' => Http::response($this->body(ml1: false), 503),
        ]);

        $this->artisan('ml:health')
            ->expectsOutputToContain('ML1: tidak siap')
            ->expectsOutputToContain('ML health gagal')
            ->assertFailed();
    }

    public function test_health_gagal_bila_respons_200_tetapi_modul_belum_siap(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/health' => Http::response($this->body(ml2: false), 200),
        ]);

        $this->artisan('ml:health')->assertFailed();
    }

    public function test_health_gagal_bila_layanan_tidak_terhubung(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->artisan('ml:health')
            ->expectsOutputToContain('ML health gagal: koneksi_gagal')
            ->assertFailed();
    }
}
