<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resep extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama_resep',
        'cara_memasak',
        'kategori_gizi',
        'estimasi_biaya',
        'porsi_saji',
        'tingkat_kesulitan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimasi_biaya' => 'float',
            'porsi_saji' => 'integer',
        ];
    }

    public function bahans(): HasMany
    {
        return $this->hasMany(ResepBahan::class);
    }

    /**
     * Hitung ulang estimasi biaya dari takaran × harga bahan.
     * NULL bila ada bahan tanpa info harga.
     */
    public function hitungUlangBiaya(): ?float
    {
        $this->loadMissing('bahans.bahan');

        $total = 0.0;

        foreach ($this->bahans as $rb) {
            $harga = $rb->bahan->estimasi_harga_per100g;

            if ($harga === null) {
                return null;
            }

            $total += ((float) $rb->takaran_gram) / 100.0 * ((float) $harga);
        }

        return round($total, 2);
    }

    public function biayaLengkap(): bool
    {
        $this->loadMissing('bahans.bahan');

        foreach ($this->bahans as $rb) {
            if ($rb->bahan->estimasi_harga_per100g === null) {
                return false;
            }
        }

        return true;
    }
}
