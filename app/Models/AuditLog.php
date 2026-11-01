<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'aksi',
        'entitas_target',
        'entitas_id',
        'nilai_lama',
        'nilai_baru',
        'meta',
        'waktu_aksi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nilai_lama' => 'array',
            'nilai_baru' => 'array',
            'meta' => 'array',
            'waktu_aksi' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
