<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pertumbuhan extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'tanggal_ukur',
        'umur_bulan',
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
}
