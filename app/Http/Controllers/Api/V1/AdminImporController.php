<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Pertumbuhan;
use App\Services\ZScoreService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminImporController extends Controller
{
    private const MAKS_BARIS = 1000;

    /**
     * Impor profil anak dari CSV register posyandu (admin).
     * Header wajib: nama,tanggal_lahir,jenis_kelamin,wilayah_posyandu,user_id
     * Header opsional: alamat
     */
    public function imporAnak(Request $request): JsonResponse
    {
        $this->wajibAdmin($request);
        $rows = $this->bacaCsv($request, ['nama', 'tanggal_lahir', 'jenis_kelamin', 'wilayah_posyandu', 'user_id'], ['alamat']);

        $valid = [];
        $galat = [];

        foreach ($rows as $i => $row) {
            $baris = $i + 2; // +1 header, +1 basis-1
            $v = Validator::make($row, [
                'nama' => ['required', 'string', 'max:100'],
                'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
                'jenis_kelamin' => ['required', 'in:L,P'],
                'wilayah_posyandu' => ['required', 'string', 'max:100', 'exists:wilayahs,nama'],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'alamat' => ['nullable', 'string', 'max:500'],
            ]);

            if ($v->fails()) {
                $galat[] = ['baris' => $baris, 'pesan' => $v->errors()->first()];

                continue;
            }

            $valid[] = [...$v->validated(), 'alamat' => $v->validated()['alamat'] ?? null];
        }

        $ids = DB::transaction(function () use ($request, $valid) {
            $dibuat = [];

            foreach ($valid as $data) {
                $anak = Anak::create($data);
                $dibuat[] = $anak->id;
            }

            AuditLogger::log($request->user(), 'impor_anak', [
                'berhasil' => count($dibuat),
            ]);

            return $dibuat;
        });

        return ApiResponse::success(
            $this->ringkasan(count($rows), $ids, $galat),
            'Impor profil anak selesai.'
        );
    }

    /**
     * Impor histori pengukuran dari CSV (admin). Z-score dihitung otomatis.
     * Header wajib: anak_id,tanggal_ukur,berat_badan_kg,tinggi_badan_cm
     * Header opsional: posisi_ukur
     */
    public function imporUkur(Request $request, ZScoreService $zscore): JsonResponse
    {
        $this->wajibAdmin($request);
        $rows = $this->bacaCsv($request, ['anak_id', 'tanggal_ukur', 'berat_badan_kg', 'tinggi_badan_cm'], ['posisi_ukur']);

        $valid = [];
        $galat = [];

        foreach ($rows as $i => $row) {
            $baris = $i + 2;
            $v = Validator::make($row, [
                'anak_id' => ['required', 'uuid', 'exists:anaks,id'],
                'tanggal_ukur' => ['required', 'date', 'before_or_equal:today'],
                'berat_badan_kg' => ['required', 'numeric', 'min:0.5', 'max:40'],
                'tinggi_badan_cm' => ['required', 'numeric', 'min:30', 'max:150'],
                'posisi_ukur' => ['nullable', 'in:terlentang,berdiri'],
            ]);

            if ($v->fails()) {
                $galat[] = ['baris' => $baris, 'pesan' => $v->errors()->first()];

                continue;
            }

            $valid[] = ['baris' => $baris, ...$v->validated()];
        }

        // Hitung Z-score untuk baris valid; kegagalan kalkulasi jadi galat baris.
        $siap = [];
        foreach ($valid as $data) {
            $anak = Anak::find($data['anak_id']);

            try {
                $hasil = $zscore->hitung(
                    $anak,
                    (float) $data['berat_badan_kg'],
                    (float) $data['tinggi_badan_cm'],
                    $data['tanggal_ukur'],
                    $data['posisi_ukur'] ?? null,
                );
            } catch (ValidationException $e) {
                $galat[] = ['baris' => $data['baris'], 'pesan' => $e->validator->errors()->first()];

                continue;
            }

            $siap[] = [
                'anak_id' => $anak->id,
                'tanggal_ukur' => $data['tanggal_ukur'],
                'umur_bulan' => $hasil['umur_bulan'],
                'berat_kg' => $data['berat_badan_kg'],
                'tinggi_cm' => $data['tinggi_badan_cm'],
                'tinggi_koreksi_cm' => $hasil['tinggi_koreksi_cm'],
                'posisi_ukur' => $data['posisi_ukur'] ?? null,
                'z_bb_u' => $hasil['z_bb_u'],
                'z_tb_u' => $hasil['z_tb_u'],
                'z_bb_tb' => $hasil['z_bb_tb'],
                'status_bb_u' => $hasil['status_bb_u'],
                'status_tb_u' => $hasil['status_tb_u'],
                'status_bb_tb' => $hasil['status_bb_tb'],
                'status_gizi_antropometri' => $hasil['status_gizi_antropometri'],
                'usia_bulan_saat_ukur' => $hasil['umur_bulan'],
                'kategori_pemantauan' => $hasil['kategori_pemantauan'],
                'metode_klasifikasi' => $hasil['metode_klasifikasi'],
                'versi_aturan' => $hasil['versi_aturan'],
                'status_validitas_pengukuran' => Pertumbuhan::VALID,
                'alasan_pengecualian' => null,
                'dicatat_oleh' => $request->user()->id,
            ];
        }

        $ids = DB::transaction(function () use ($request, $siap) {
            $dibuat = [];

            foreach ($siap as $data) {
                $dibuat[] = Pertumbuhan::create($data)->id;
            }

            AuditLogger::log($request->user(), 'impor_ukur', [
                'berhasil' => count($dibuat),
            ]);

            return $dibuat;
        });

        return ApiResponse::success(
            $this->ringkasan(count($rows), $ids, $galat),
            'Impor pengukuran selesai.'
        );
    }

    private function wajibAdmin(Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            throw new HttpResponseException(ApiResponse::error(
                'FORBIDDEN_RESOURCE', 'Hanya admin yang dapat melakukan impor data.', 403
            ));
        }
    }

    /**
     * @param  list<string>  $wajib
     * @param  list<string>  $opsional
     * @return list<array<string, ?string>>
     */
    private function bacaCsv(Request $request, array $wajib, array $opsional): array
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $isi = file_get_contents($file->getRealPath());
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', (string) $isi); // BOM
        $baris = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\n|\r/', $isi)),
            fn ($b) => $b !== ''
        ));

        if ($baris === []) {
            throw new HttpResponseException(ApiResponse::error(
                'VALIDATION_ERROR', 'Berkas CSV kosong.', 400
            ));
        }

        $header = array_map('trim', str_getcsv(array_shift($baris)));
        $hilang = array_diff($wajib, $header);

        if ($hilang !== []) {
            throw new HttpResponseException(ApiResponse::error(
                'VALIDATION_ERROR',
                'Header CSV tidak lengkap. Kurang: '.implode(', ', $hilang)
                    .'. Wajib: '.implode(', ', $wajib)
                    .($opsional !== [] ? '. Opsional: '.implode(', ', $opsional) : '').'.',
                400
            ));
        }

        if (count($baris) > self::MAKS_BARIS) {
            throw new HttpResponseException(ApiResponse::error(
                'VALIDATION_ERROR', 'Maksimal '.self::MAKS_BARIS.' baris data per impor.', 400
            ));
        }

        $rows = [];
        foreach ($baris as $b) {
            $sel = str_getcsv($b);
            $row = [];

            foreach ($header as $i => $kolom) {
                if (! in_array($kolom, [...$wajib, ...$opsional], true)) {
                    continue;
                }
                $nilai = trim((string) ($sel[$i] ?? ''));
                $row[$kolom] = $nilai === '' ? null : $nilai;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $ids
     * @param  list<array{baris: int, pesan: string}>  $galat
     * @return array{total_baris: int, berhasil: int, gagal: int, ids: list<string>, galat: list<array{baris: int, pesan: string}>}
     */
    private function ringkasan(int $total, array $ids, array $galat): array
    {
        usort($galat, fn ($a, $b) => $a['baris'] <=> $b['baris']);

        return [
            'total_baris' => $total,
            'berhasil' => count($ids),
            'gagal' => count($galat),
            'ids' => $ids,
            'galat' => $galat,
        ];
    }
}
