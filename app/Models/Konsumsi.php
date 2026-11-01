<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Konsumsi extends Model
{
    use HasUuids;

    public const METODE_MANUAL = 'MANUAL';

    public const METODE_RESEP = 'RESEP';

    public const VALID = 'VALID';

    public const INVALID = 'INVALID';

    public const ALASAN_ANAK_SAKIT = 'ANAK_SAKIT';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'kondisi_id',
        'bahan_id',
        'resep_id',
        'porsi_gram',
        'porsi_resep_dikonsumsi',
        'tanggal_konsumsi',
        'metode_input',
        'status_validitas',
        'alasan_invalid',
        'dicatat_oleh',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_konsumsi' => 'date',
            'porsi_gram' => 'float',
            'porsi_resep_dikonsumsi' => 'float',
        ];
    }

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class);
    }

    public function kondisi(): BelongsTo
    {
        return $this->belongsTo(KondisiHarian::class, 'kondisi_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanMakanan::class, 'bahan_id');
    }

    public function resep(): BelongsTo
    {
        return $this->belongsTo(Resep::class);
    }
}
