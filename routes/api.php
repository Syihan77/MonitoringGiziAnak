<?php

use App\Http\Controllers\Api\V1\AnakController;
use App\Http\Controllers\Api\V1\AdminImporController;
use App\Http\Controllers\Api\V1\AdminReferensiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\KaderController;
use App\Http\Controllers\Api\V1\KonsumsiController;
use App\Http\Controllers\Api\V1\PertumbuhanController;
use App\Http\Controllers\Api\V1\RekomendasiController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

// Kontrak API berversi eksplisit (SKPL §4.6.2): /api/v1/...
// SKPL §5.4.1: rate limit 120 permintaan/menit (429 RATE_LIMIT_EXCEEDED).
Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::get('/ping', fn () => ApiResponse::success(['time' => now()->toIso8601String()], 'API v1 aktif'));

    // SKPL-F-01 & F-02: autentikasi.
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // SKPL-F-03 & F-04: modul anak & pertumbuhan (Tahap C).
        Route::get('/anak', [AnakController::class, 'index']);
        Route::post('/anak', [AnakController::class, 'store']);
        Route::get('/anak/{id}', [AnakController::class, 'show']);
        Route::match(['put', 'patch'], '/anak/{id}', [AnakController::class, 'update']);
        Route::delete('/anak/{id}', [AnakController::class, 'destroy']);

        Route::post('/anak/{anakId}/pertumbuhan', [PertumbuhanController::class, 'store']);
        Route::get('/anak/{anakId}/pertumbuhan', [PertumbuhanController::class, 'index']);

        // SKPL-F-10: dashboard & logbook kader (Fase 2).
        Route::get('/kader/dashboard', [KaderController::class, 'dashboard']);
        Route::get('/kader/anak/{anakId}/logbook', [KaderController::class, 'logbook']);

        // Fase 2: impor CSV register posyandu (admin).
        Route::post('/admin/impor-anak', [AdminImporController::class, 'imporAnak']);
        Route::post('/admin/impor-ukur', [AdminImporController::class, 'imporUkur']);

        // SKPL-F-06 & F-07: konsumsi harian & analisis gizi (Fase 2).
        Route::post('/anak/{anakId}/konsumsi', [KonsumsiController::class, 'store']);
        Route::get('/anak/{anakId}/konsumsi', [KonsumsiController::class, 'index']);
        Route::get('/anak/{anakId}/analisis-gizi', [KonsumsiController::class, 'analisis']);
        Route::get('/anak/{anakId}/gizi-bulanan', [KonsumsiController::class, 'bulanan']);

        // SKPL-F-08 & F-09: rekomendasi menu & detail resep (Fase 2).
        Route::post('/anak/{anakId}/rekomendasi-menu', [RekomendasiController::class, 'store']);
        Route::get('/resep/{resepId}', [RekomendasiController::class, 'show']);

        // SKPL-F-11 & F-12: referensi bahan & resep (baca: semua peran; tulis: admin).
        Route::get('/bahan-makanan', [AdminReferensiController::class, 'bahanIndex']);
        Route::post('/bahan-makanan', [AdminReferensiController::class, 'bahanStore']);
        Route::match(['put', 'patch'], '/bahan-makanan/{id}', [AdminReferensiController::class, 'bahanUpdate']);
        Route::delete('/bahan-makanan/{id}', [AdminReferensiController::class, 'bahanDestroy']);
        Route::get('/resep', [AdminReferensiController::class, 'resepIndex']);
        Route::post('/resep', [AdminReferensiController::class, 'resepStore']);
        Route::match(['put', 'patch'], '/resep/{id}', [AdminReferensiController::class, 'resepUpdate']);
        Route::delete('/resep/{id}', [AdminReferensiController::class, 'resepDestroy']);
    });
});
