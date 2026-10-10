<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Pencatatan audit sinkron atas aktivitas penting (SKPL §6.5.2).
 */
class AuditLogger
{
    public static function log(?User $user, string $aksi, array $meta = []): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'meta' => $meta ?: null,
            'waktu_aksi' => now(),
        ]);
    }
}
