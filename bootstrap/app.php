<?php

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // SKPL §5.4.11: validasi gagal -> 400 VALIDATION_ERROR (envelope standar).
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'VALIDATION_ERROR',
                'Data yang dikirim tidak valid.',
                400,
                ['errors' => $e->errors()],
            );
        });

        // SKPL §5.4.11: otorisasi gagal -> 403 FORBIDDEN_RESOURCE (envelope standar).
        // Gate melempar AuthorizationException yang dipetakan kernel menjadi
        // AccessDeniedHttpException sebelum renderer dipanggil.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'FORBIDDEN_RESOURCE',
                'Anda tidak memiliki akses ke data ini.',
                403,
            );
        });

        // SKPL §5.4.11: rate limit -> 429 RATE_LIMIT_EXCEEDED (envelope standar).
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'RATE_LIMIT_EXCEEDED',
                'Terlalu banyak permintaan. Coba lagi sesaat lagi.',
                429,
            );
        });

        // SKPL §5.4.11: token kedaluwarsa -> 401 TOKEN_EXPIRED,
        // token tidak ada/tidak valid -> 401 UNAUTHENTICATED.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $expired = false;
            if ($request->bearerToken()) {
                try {
                    $record = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
                    $expired = $record !== null
                        && $record->expires_at !== null
                        && $record->expires_at->isPast();
                } catch (\Throwable) {
                    $expired = false;
                }
            }

            if ($expired) {
                return ApiResponse::error(
                    'TOKEN_EXPIRED',
                    'Token telah kedaluwarsa. Silakan login kembali.',
                    401,
                );
            }

            return ApiResponse::error(
                'UNAUTHENTICATED',
                'Token tidak ada atau tidak valid.',
                401,
            );
        });

        // SKPL §5.4.11: fallback 500 INTERNAL_ERROR (envelope standar).
        // Didaftarkan terakhir agar renderer spesifik menang lebih dulu;
        // HttpResponseException (mis. NOT_FOUND dari controller) diteruskan.
        $exceptions->render(function (\Exception $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpResponseException) {
                return null;
            }

            return ApiResponse::error(
                'INTERNAL_ERROR',
                'Terjadi kesalahan pada server.',
                500,
            );
        });
    })->create();

