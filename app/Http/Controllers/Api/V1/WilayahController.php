<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WilayahController extends Controller
{
    /**
     * Daftar wilayah posyandu unik untuk pilihan filter/form.
     */
    public function index(): JsonResponse
    {
        $wilayah = User::query()
            ->whereNotNull('wilayah_posyandu')
            ->where('wilayah_posyandu', '!=', '')
            ->distinct()
            ->orderBy('wilayah_posyandu')
            ->pluck('wilayah_posyandu')
            ->values();

        return ApiResponse::success($wilayah, 'Daftar wilayah posyandu');
    }
}
