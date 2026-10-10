<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RekomendasiLog extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'tanggal_minta',
        'masukan',
        'defisiensi',
        'jumlah_hasil',
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
        ];
    }
}
