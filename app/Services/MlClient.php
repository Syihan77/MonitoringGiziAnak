<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MlClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, data: array<string, mixed>|null, status: int|null, alasan: string|null}
     */
    public function prediksiMl1(array $payload): array
    {
        $mulai = hrtime(true);
        $status = null;
        $alasan = null;
        $data = null;

        try {
            $response = Http::baseUrl((string) config('services.ml.ml1.base_url'))
                ->connectTimeout((int) config('services.ml.ml1.connect_timeout', 1))
                ->timeout((int) config('services.ml.ml1.timeout', 2))
                ->acceptJson()
                ->asJson()
                ->post('/ml1/prediksi', $payload);

            $status = $response->status();

            if ($response->failed()) {
                $alasan = $status >= 500 ? 'http_5xx' : 'http_4xx';
            } else {
                $data = $this->parseBody($response->body(), $alasan);
            }
        } catch (ConnectionException $e) {
            $alasan = $this->alasanKoneksi($e);
        } catch (Throwable) {
            $alasan = 'kesalahan_tidak_terduga';
        }

        $ok = $alasan === null && is_array($data);
        $durasi = (int) round((hrtime(true) - $mulai) / 1_000_000);

        Log::info('ml.call', [
            'modul' => 'ML1',
            'status' => $ok ? 'ok' : (string) $alasan,
            'durasi_ms' => $durasi,
        ]);

        return [
            'ok' => $ok,
            'data' => $ok ? $data : null,
            'status' => $status,
            'alasan' => $ok ? null : $alasan,
        ];
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>|null, status: int|null, alasan: string|null}
     */
    public function health(): array
    {
        $mulai = hrtime(true);
        $status = null;
        $alasan = null;
        $data = null;

        try {
            $response = Http::baseUrl((string) config('services.ml.ml2.base_url'))
                ->connectTimeout((int) config('services.ml.ml2.connect_timeout', 1))
                ->timeout((int) config('services.ml.ml2.timeout', 5))
                ->acceptJson()
                ->get('/health');

            $status = $response->status();
            $data = json_decode($response->body(), true);

            if (! is_array($data)) {
                $alasan = 'json_rusak';
            } elseif (! $response->successful()) {
                $alasan = $status >= 500 ? 'http_5xx' : 'http_4xx';
            } elseif (($data['status'] ?? null) !== 'ok'
                || ($data['modules']['ml1']['ready'] ?? false) !== true
                || ($data['modules']['ml2']['ready'] ?? false) !== true) {
                $alasan = 'modul_tidak_siap';
            }
        } catch (ConnectionException $e) {
            $alasan = $this->alasanKoneksi($e);
        } catch (Throwable) {
            $alasan = 'kesalahan_tidak_terduga';
        }

        $ok = $alasan === null && is_array($data);
        $durasi = (int) round((hrtime(true) - $mulai) / 1_000_000);

        Log::info('ml.health', [
            'status' => $ok ? 'ok' : (string) $alasan,
            'durasi_ms' => $durasi,
        ]);

        return [
            'ok' => $ok,
            'data' => $ok ? $data : (is_array($data) ? $data : null),
            'status' => $status,
            'alasan' => $ok ? null : $alasan,
        ];
    }

    /**
     * ML2 hanya mengembalikan peringkat relatif; keputusan akhir tetap di Laravel.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, data: array<string, mixed>|null, status: int|null, alasan: string|null}
     */
    public function rekomendasiMl2(array $payload): array
    {
        $mulai = hrtime(true);
        $status = null;
        $alasan = null;
        $data = null;

        try {
            $response = Http::baseUrl((string) config('services.ml.ml2.base_url'))
                ->connectTimeout((int) config('services.ml.ml2.connect_timeout', 1))
                ->timeout((int) config('services.ml.ml2.timeout', 5))
                ->acceptJson()
                ->asJson()
                ->post('/ml2/rekomendasi', $payload);

            $status = $response->status();

            if ($response->failed()) {
                $alasan = $status >= 500 ? 'http_5xx' : 'http_4xx';
            } else {
                $data = $this->parseBodyMl2($response->body(), $alasan);
            }
        } catch (ConnectionException $e) {
            $alasan = $this->alasanKoneksi($e);
        } catch (Throwable) {
            $alasan = 'kesalahan_tidak_terduga';
        }

        $ok = $alasan === null && is_array($data);
        $durasi = (int) round((hrtime(true) - $mulai) / 1_000_000);

        Log::info('ml.call', [
            'modul' => 'ML2',
            'status' => $ok ? 'ok' : (string) $alasan,
            'durasi_ms' => $durasi,
        ]);

        return [
            'ok' => $ok,
            'data' => $ok ? $data : null,
            'status' => $status,
            'alasan' => $ok ? null : $alasan,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseBody(string $body, ?string &$alasan): ?array
    {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $alasan = 'json_rusak';

            return null;
        }

        if (! is_array($data) || ! $this->bentukMl1Valid($data)) {
            $alasan = 'respons_tidak_valid';

            return null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseBodyMl2(string $body, ?string &$alasan): ?array
    {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $alasan = 'json_rusak';

            return null;
        }

        if (! is_array($data) || ! $this->bentukMl2Valid($data)) {
            $alasan = 'respons_tidak_valid';

            return null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bentukMl2Valid(array $data): bool
    {
        if (! is_array($data['meta'] ?? null) || ! is_array($data['hasil'] ?? null)) {
            return false;
        }

        foreach ($data['hasil'] as $item) {
            if (! is_array($item) || ! is_string($item['id_resep'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bentukMl1Valid(array $data): bool
    {
        return is_string($data['versi_model'] ?? null)
            && $data['versi_model'] !== ''
            && is_string($data['status_model'] ?? null)
            && $data['status_model'] !== ''
            && is_array($data['acuan'] ?? null)
            && isset($data['acuan']['kategori'])
            && is_array($data['prediksi_ml'] ?? null)
            && isset($data['prediksi_ml']['kategori']);
    }

    private function alasanKoneksi(ConnectionException $e): string
    {
        $pesan = strtolower($e->getMessage());

        if (
            str_contains($pesan, 'connection timed out')
            || str_contains($pesan, 'failed to connect')
            || str_contains($pesan, 'could not connect')
            || str_contains($pesan, 'connection refused')
            || str_contains($pesan, 'could not resolve')
        ) {
            return 'koneksi_gagal';
        }

        return str_contains($pesan, 'timed out') || str_contains($pesan, 'curl error 28')
            ? 'timeout'
            : 'koneksi_gagal';
    }
}
