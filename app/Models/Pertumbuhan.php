<?php

namespace App\Models;

use App\Services\ZScoreService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pertumbuhan extends Model
{
    use HasUuids;

    public const KATEGORI_NORMAL_LOW_RISK = 'NORMAL_LOW_RISK';

    public const KATEGORI_AT_RISK_MONITORING = 'AT_RISK_MONITORING';

    public const KATEGORI_HIGH_RISK_REFERRAL = 'HIGH_RISK_REFERRAL';

    public const METODE_ACUAN_ATURAN = 'ACUAN_ATURAN';

    public const METODE_MODEL_ML = 'MODEL_ML';

    public const VALID = 'VALID';

    public const DIKECUALIKAN = 'DIKECUALIKAN';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'tanggal_ukur',
        'umur_bulan',
        'usia_bulan_saat_ukur',
        'berat_kg',
        'tinggi_cm',
        'tinggi_koreksi_cm',
        'posisi_ukur',
        'z_bb_u',
        'z_tb_u',
        'z_bb_tb',
        'status_bb_u',
        'status_tb_u',
        'status_bb_tb',
        'status_gizi_antropometri',
        'kategori_pemantauan',
        'metode_klasifikasi',
        'versi_aturan',
        'status_validitas_pengukuran',
        'alasan_pengecualian',
        'dicatat_oleh',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_ukur' => 'date',
            'umur_bulan' => 'integer',
            'usia_bulan_saat_ukur' => 'decimal:2',
            'berat_kg' => 'decimal:2',
            'tinggi_cm' => 'decimal:1',
            'tinggi_koreksi_cm' => 'decimal:1',
            'z_bb_u' => 'decimal:2',
            'z_tb_u' => 'decimal:2',
            'z_bb_tb' => 'decimal:2',
        ];
    }

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class);
    }

    public function kategoriEfektif(): ?string
    {
        if ($this->kategori_pemantauan !== null) {
            return $this->kategori_pemantauan;
        }

        if ($this->z_bb_u === null || $this->z_tb_u === null || $this->z_bb_tb === null) {
            return null;
        }

        return ZScoreService::kategoriPemantauan(
            (float) $this->z_bb_u,
            (float) $this->z_tb_u,
            (float) $this->z_bb_tb,
        );
    }

    public function metodeKlasifikasiEfektif(): string
    {
        return $this->metode_klasifikasi ?? self::METODE_ACUAN_ATURAN;
    }

    public function versiAturanEfektif(): string
    {
        return $this->versi_aturan ?? ZScoreService::VERSI_ATURAN;
    }

    public function statusValiditasEfektif(): string
    {
        return $this->status_validitas_pengukuran ?? self::VALID;
    }

    public function pesanEfektif(): string
    {
        $kategori = $this->kategoriEfektif();

        return $kategori === null
            ? ZScoreService::PESAN_UMUM
            : ZScoreService::pesanKategori($kategori);
    }
}
