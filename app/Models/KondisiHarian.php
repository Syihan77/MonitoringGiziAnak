<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KondisiHarian extends Model
{
    use HasUuids;

    public const STATUS_SAKIT = 'SAKIT';

    public const STATUS_TIDAK_SAKIT = 'TIDAK_SAKIT';

    protected $table = 'kondisi_harian';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'tanggal_kondisi',
        'status_kondisi',
        'dicatat_oleh',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_kondisi' => 'date',
        ];
    }

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function konsumsis(): HasMany
    {
        return $this->hasMany(Konsumsi::class, 'kondisi_id');
    }
}
