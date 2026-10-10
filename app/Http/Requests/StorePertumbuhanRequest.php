<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePertumbuhanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'berat_badan_kg' => ['required', 'numeric', 'min:0.5', 'max:40'],
            'tinggi_badan_cm' => ['required', 'numeric', 'min:30', 'max:150'],
            'tanggal_ukur' => ['required', 'date', 'before_or_equal:today'],
            // Opsional (keputusan proyek): null = tanpa koreksi posisi.
            'posisi_ukur' => ['nullable', 'in:terlentang,berdiri'],
        ];
    }
}
