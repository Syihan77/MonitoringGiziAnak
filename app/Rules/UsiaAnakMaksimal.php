<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Batas usia anak 0-60 bulan (baku WHO) dihitung dari tanggal lahir.
 *
 * Usia dibulatkan ke bawah dalam bulan penuh, sama seperti
 * ZScoreService::umurBulanPenuh(), sehingga anak berusia tepat 60 bulan
 * masih diterima dan 61 bulan ditolak.
 */
class UsiaAnakMaksimal implements ValidationRule
{
    public function __construct(private readonly int $maksBulan = 60) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            $lahir = CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return; // Format tanggal tidak valid ditangani aturan date().
        }

        $hariIni = CarbonImmutable::now()->startOfDay();

        if ($lahir->greaterThan($hariIni)) {
            return; // Tanggal masa depan ditangani before_or_equal:today.
        }

        if ((int) $lahir->diffInMonths($hariIni) > $this->maksBulan) {
            $fail("Usia anak tidak boleh lebih dari {$this->maksBulan} bulan.");
        }
    }
}
