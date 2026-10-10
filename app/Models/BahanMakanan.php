<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BahanMakanan extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama_bahan',
        'kelompok_gizi',
        'energi_per100g',
        'karbohidrat_per100g',
        'protein_per100g',
        'lemak_per100g',
        'zat_besi_per100g',
        'zinc_per100g',
        'estimasi_harga_per100g',
        'sumber_rujukan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'energi_per100g' => 'float',
            'karbohidrat_per100g' => 'float',
            'protein_per100g' => 'float',
            'lemak_per100g' => 'float',
            'zat_besi_per100g' => 'float',
            'zinc_per100g' => 'float',
            'estimasi_harga_per100g' => 'float',
        ];
    }
}
