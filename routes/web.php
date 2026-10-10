<?php

use Illuminate\Support\Facades\Route;

// NutriTumbuh — halaman statis (hasil migrasi frontend vanilla).
// Proteksi auth menyusul setelah API Sanctum (Fase 1) jadi.
Route::view('/', 'index');
Route::view('/daftar', 'daftar');
Route::view('/login', 'login')->name('login');
Route::view('/kebijakan', 'kebijakan');

// Alur Kader
Route::view('/data-kader', 'data-kader');
Route::view('/review-kader', 'review-kader');
Route::view('/db-kader', 'db-kader');
Route::view('/perhatian-kader', 'perhatian-kader');
Route::view('/riwayat-kader', 'riwayat-kader');
Route::view('/ukur-kader', 'ukur-kader');

// Alur Orang Tua
Route::view('/data-ortu', 'data-ortu');
Route::view('/data-dirianak', 'data-dirianak');
Route::view('/data-pertumbuhan', 'data-pertumbuhan');
Route::view('/review-ortu', 'review-ortu');
Route::view('/db-ortu', 'db-ortu');
Route::view('/input-ortu', 'input-ortu');
Route::view('/history-ortu', 'history-ortu');
Route::view('/resep-detail', 'resep-detail');
