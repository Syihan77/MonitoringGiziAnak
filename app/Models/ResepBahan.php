<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResepBahan extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'resep_id',
        'bahan_id',
        'takaran_gram',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'takaran_gram' => 'float',
        ];
    }

    public function resep(): BelongsTo
    {
        return $this->belongsTo(Resep::class);
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanMakanan::class, 'bahan_id');
    }
}
