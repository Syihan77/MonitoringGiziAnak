<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Anak extends Model
{
    use HasFactory, HasUuids;

    public const JK_LAKI = 'L';

    public const JK_PEREMPUAN = 'P';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nama',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pertumbuhans(): HasMany
    {
        return $this->hasMany(Pertumbuhan::class)->orderBy('tanggal_ukur');
    }
}
