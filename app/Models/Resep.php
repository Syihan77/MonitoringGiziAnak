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
     * Status DATA_LENGKAP hanya mensyaratkan empat makronutrisi per porsi.
     *
     * @var list<string>
     */
    public const MAKRO_WAJIB = [
        'energi_per_porsi',
        'karbohidrat_per_porsi',
        'protein_per_porsi',
        'lemak_per_porsi',
    ];

    public const CATATAN_MAKRO_TIDAK_LENGKAP = 'Catatan data: energi, karbohidrat, protein, dan lemak per porsi wajib lengkap untuk status DATA_LENGKAP; status diturunkan ke PERLU_VERIFIKASI.';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama_resep',
        'cara_memasak',
        'kategori_gizi',
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
        'usia_label_bulan',
        'usia_maks_bulan',
        'tekstur_sajian',
        'sumber_resep',
        'status_verifikasi_data',
        'nama_pemverifikasi',
        'tanggal_verifikasi',
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
            'usia_label_bulan' => 'integer',
            'usia_maks_bulan' => 'integer',
            'usia_minimal_bulan_jika_dimodifikasi' => 'integer',
            'tanggal_verifikasi' => 'date',
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

    public function makroLengkap(): bool
    {
        foreach (self::MAKRO_WAJIB as $kolom) {
            if ($this->{$kolom} === null) {
                return false;
            }
        }

        return true;
    }

    public static function tambahkanAlasanMakro(?string $catatan): string
    {
        $catatan = trim((string) $catatan);

        if (str_contains($catatan, self::CATATAN_MAKRO_TIDAK_LENGKAP)) {
            return $catatan;
        }

        return $catatan === ''
            ? self::CATATAN_MAKRO_TIDAK_LENGKAP
            : $catatan.' '.self::CATATAN_MAKRO_TIDAK_LENGKAP;
    }

    public function turunkanKePerluVerifikasiKarenaMakro(): bool
    {
        if ($this->makroLengkap()) {
            return false;
        }

        $atribut = [];

        if ($this->status_verifikasi_data !== self::VERIFIKASI_PERLU_VERIFIKASI) {
            $atribut['status_verifikasi_data'] = self::VERIFIKASI_PERLU_VERIFIKASI;
        }

        $catatan = self::tambahkanAlasanMakro($this->catatan_resep);
        if ($catatan !== (string) $this->catatan_resep) {
            $atribut['catatan_resep'] = $catatan;
        }

        if ($atribut === []) {
            return false;
        }

        $this->update($atribut);

        return true;
    }

    public function layakRekomendasi(bool $bolehDemo = false): bool
    {
        return $this->terverifikasi()
            || ($bolehDemo && $this->dataLengkap());
    }

    public function bahans(): HasMany
    {
        return $this->hasMany(ResepBahan::class);
    }
}
