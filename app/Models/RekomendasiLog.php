<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiLog extends Model
{
    use HasUuids;

    public const METODE_BASELINE_ATURAN = 'BASELINE_ATURAN';

    public const METODE_MODEL_ML = 'MODEL_ML';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'resep_id',
        'tanggal_minta',
        'masukan',
        'defisiensi',
        'fokus_gizi',
        'jumlah_hasil',
        'peringkat',
        'skor_gizi_umum',
        'skor_relevansi',
        'skor_akhir',
        'metode_ranking',
        'versi_model_atau_aturan',
        'alasan_peringkat',
        'tanggal_rekomendasi',
        'diminta_oleh',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_minta' => 'date',
            'masukan' => 'array',
            'defisiensi' => 'array',
            'jumlah_hasil' => 'integer',
            'peringkat' => 'integer',
            'skor_gizi_umum' => 'float',
            'skor_relevansi' => 'float',
            'skor_akhir' => 'float',
            'tanggal_rekomendasi' => 'datetime',
        ];
    }

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class);
    }

    public function resep(): BelongsTo
    {
        return $this->belongsTo(Resep::class);
    }
}
