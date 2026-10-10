<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * SKPL-F-01: registrasi akun orang tua / kader.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Consent dicek eksplisit agar selalu 400 CONSENT_REQUIRED (SKPL §5.4.11).
        if (! $request->boolean('consent_approved')) {
            return ApiResponse::error(
                'CONSENT_REQUIRED',
                'Registrasi ditolak karena persetujuan pengolahan data belum diberikan.',
                400,
            );
        }

        $user = User::create($request->validated());

        AuditLogger::log($user, 'register', ['role' => $user->role]);

        return ApiResponse::success(
            ['user_id' => $user->id, 'role' => $user->role],
            'Registrasi berhasil',
            201,
        );
    }

    /**
     * SKPL-F-02: login, terbitkan token Sanctum 24 jam.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            AuditLogger::log(null, 'login_gagal', ['email' => $request->string('email')->toString()]);

            return ApiResponse::error(
                'INVALID_CREDENTIALS',
                'Email atau password salah',
                401,
            );
        }

        $token = $user->createToken('api', ['*'], now()->addHours(24));

        AuditLogger::log($user, 'login');

        return ApiResponse::success(
            [
                'token' => $token->plainTextToken,
                'role' => $user->role,
                'user_id' => $user->id,
            ],
            'Login berhasil',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();

        AuditLogger::log($user, 'logout');

        return ApiResponse::success([], 'Logout berhasil');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(
            [
                'user_id' => $user->id,
                'nama' => $user->nama,
                'email' => $user->email,
                'role' => $user->role,
                'wilayah_posyandu' => $user->wilayah_posyandu,
                'no_telp' => $user->no_telp,
            ],
            'Profil pengguna',
        );
    }
}
