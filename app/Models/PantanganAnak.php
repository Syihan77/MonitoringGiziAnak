<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PantanganAnak extends Model
{
    use HasUuids;

    public const JENIS_ALERGI = 'ALERGI';

    public const JENIS_PANTANGAN = 'PANTANGAN';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'bahan_id',
        'alergen',
        'jenis',
    ];

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class);
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanMakanan::class, 'bahan_id');
    }
}
