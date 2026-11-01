<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAnakRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'pantangan' => ['sometimes', 'array', 'max:50'],
            'pantangan.*.bahan_id' => ['nullable', 'uuid', 'exists:bahan_makanans,id'],
            'pantangan.*.alergen' => ['nullable', 'string', 'max:50'],
            'pantangan.*.jenis' => ['sometimes', 'in:ALERGI,PANTANGAN'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pantangan = $this->input('pantangan', []);

            if (! is_array($pantangan)) {
                return;
            }

            foreach ($pantangan as $index => $item) {
                if (blank($item['bahan_id'] ?? null) && blank($item['alergen'] ?? null)) {
                    $validator->errors()->add(
                        "pantangan.{$index}",
                        'Isi bahan atau nama alergen pada setiap pantangan.'
                    );
                }
            }
        });
    }
}
