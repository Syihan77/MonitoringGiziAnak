<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const ROLE_ORANG_TUA = 'orang_tua';

    public const ROLE_KADER = 'kader';

    public const ROLE_ADMIN = 'admin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'email',
        'password',
        'role',
        'wilayah_posyandu',
        'no_telp',
        'consent_approved',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'consent_approved' => 'boolean',
        ];
    }

    public function isOrangTua(): bool
    {
        return $this->role === self::ROLE_ORANG_TUA;
    }

    public function isKader(): bool
    {
        return $this->role === self::ROLE_KADER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function anaks(): HasMany
    {
        return $this->hasMany(Anak::class);
    }
}
