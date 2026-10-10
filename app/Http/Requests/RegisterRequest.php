<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'email:rfc', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'role' => ['required', 'in:orang_tua,kader'],
            // SKPL §5.4.3: wilayah wajib apabila role=kader; harus terdaftar
            // di master wilayahs (Fase 2, ejaan baku untuk agregat desa).
            'wilayah_posyandu' => ['nullable', 'string', 'max:100', 'required_if:role,kader', 'exists:wilayahs,nama'],
            'no_telp' => ['nullable', 'string', 'max:20'],
            // consent dicek eksplisit di controller agar false -> 400 CONSENT_REQUIRED.
            'consent_approved' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah digunakan.',
            'password.regex' => 'Password minimal 8 karakter dengan kombinasi huruf dan angka.',
            'wilayah_posyandu.required_if' => 'Wilayah posyandu wajib diisi untuk peran kader.',
            'wilayah_posyandu.exists' => 'Wilayah posyandu tidak terdaftar. Pilih dari daftar resmi desa.',
            'consent_approved.boolean' => 'Persetujuan pengolahan data wajib disetujui.',
        ];
    }
}
