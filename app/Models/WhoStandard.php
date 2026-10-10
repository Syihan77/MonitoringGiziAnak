<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhoStandard extends Model
{
    public const BB_U = 'bb_u';

    public const TB_U = 'tb_u';

    public const BB_TB = 'bb_tb';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'indikator',
        'jenis_kelamin',
        'dasar',
        'varian',
        'umur_bulan',
        'panjang_cm',
        'sd_minus3',
        'sd_minus2',
        'sd_minus1',
        'median',
        'sd_plus1',
        'sd_plus2',
        'sd_plus3',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'umur_bulan' => 'integer',
            'panjang_cm' => 'decimal:1',
            'sd_minus3' => 'float',
            'sd_minus2' => 'float',
            'sd_minus1' => 'float',
            'median' => 'float',
            'sd_plus1' => 'float',
            'sd_plus2' => 'float',
            'sd_plus3' => 'float',
        ];
    }

    /**
     * Garis SD berurutan [-3..+3] untuk interpolasi Z-score.
     *
     * @return array{thresholds: list<float>, values: list<float>}
     */
    public function sdCurve(): array
    {
        return [
            'thresholds' => [-3.0, -2.0, -1.0, 0.0, 1.0, 2.0, 3.0],
            'values' => [
                (float) $this->sd_minus3,
                (float) $this->sd_minus2,
                (float) $this->sd_minus1,
                (float) $this->median,
                (float) $this->sd_plus1,
                (float) $this->sd_plus2,
                (float) $this->sd_plus3,
            ],
        ];
    }
}
