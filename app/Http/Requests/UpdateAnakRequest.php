<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnakRequest extends FormRequest
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
            'nama' => ['sometimes', 'required', 'string', 'max:100'],
            'tanggal_lahir' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['sometimes', 'required', 'in:L,P'],
            'alamat' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
