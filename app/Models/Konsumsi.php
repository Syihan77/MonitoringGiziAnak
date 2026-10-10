<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Konsumsi extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'bahan_id',
        'porsi_gram',
        'tanggal_konsumsi',
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
        ];
    }

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class);
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanMakanan::class, 'bahan_id');
    }
}
