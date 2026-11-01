<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resep extends Model
{
    use HasFactory, HasUuids;

    public const VERIFIKASI_TERVERIFIKASI = 'TERVERIFIKASI';

    public const VERIFIKASI_PERLU_VERIFIKASI = 'PERLU_VERIFIKASI';

    public const VERIFIKASI_DATA_LENGKAP = 'DATA_LENGKAP';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama_resep',
        'cara_memasak',
        'kategori_gizi',
        'estimasi_biaya',
        'porsi_saji',
        'jumlah_porsi_hasil',
        'energi_per_porsi',
        'protein_per_porsi',
        'lemak_per_porsi',
        'karbohidrat_per_porsi',
        'serat_per_porsi',
        'kalsium_per_porsi',
        'zat_besi_per_porsi',
        'zinc_per_porsi',
        'vitamin_a_per_porsi',
        'vitamin_c_per_porsi',
        'usia_min_bulan',
        'usia_maks_bulan',
        'tekstur_sajian',
        'sumber_resep',
        'status_verifikasi_data',
        'tingkat_kesulitan',
        'id_resep',
        'usia_minimal_bulan_jika_dimodifikasi',
        'modifikasi_untuk_turunkan_usia',
        'keyakinan_label',
        'jenis_menu',
        'alergen',
        'catatan_resep',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimasi_biaya' => 'float',
            'porsi_saji' => 'integer',
            'jumlah_porsi_hasil' => 'integer',
            'energi_per_porsi' => 'float',
            'protein_per_porsi' => 'float',
            'lemak_per_porsi' => 'float',
            'karbohidrat_per_porsi' => 'float',
            'serat_per_porsi' => 'float',
            'kalsium_per_porsi' => 'float',
            'zat_besi_per_porsi' => 'float',
            'zinc_per_porsi' => 'float',
            'vitamin_a_per_porsi' => 'float',
            'vitamin_c_per_porsi' => 'float',
            'usia_min_bulan' => 'integer',
            'usia_maks_bulan' => 'integer',
            'usia_minimal_bulan_jika_dimodifikasi' => 'integer',
            'alergen' => 'array',
        ];
    }

    /**
     * @return array<string, float|null>
     */
    public function giziPerPorsi(): array
    {
        return [
            'energi' => $this->energi_per_porsi,
            'protein' => $this->protein_per_porsi,
            'lemak' => $this->lemak_per_porsi,
            'karbohidrat' => $this->karbohidrat_per_porsi,
            'serat' => $this->serat_per_porsi,
            'kalsium' => $this->kalsium_per_porsi,
            'zat_besi' => $this->zat_besi_per_porsi,
            'zinc' => $this->zinc_per_porsi,
            'vitamin_a' => $this->vitamin_a_per_porsi,
            'vitamin_c' => $this->vitamin_c_per_porsi,
        ];
    }

    public function terverifikasi(): bool
    {
        return $this->status_verifikasi_data === self::VERIFIKASI_TERVERIFIKASI;
    }

    public function dataLengkap(): bool
    {
        return $this->status_verifikasi_data === self::VERIFIKASI_DATA_LENGKAP;
    }

    public function layakRekomendasi(): bool
    {
        return in_array($this->status_verifikasi_data, [
            self::VERIFIKASI_DATA_LENGKAP,
            self::VERIFIKASI_TERVERIFIKASI,
        ], true);
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
