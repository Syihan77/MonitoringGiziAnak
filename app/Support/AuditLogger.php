<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Pencatatan audit sinkron atas aktivitas penting (SKPL §6.5.2).
 */
class AuditLogger
{
    public static function log(?User $user, string $aksi, array $meta = [], array $audit = []): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'entitas_target' => $audit['entitas_target'] ?? null,
            'entitas_id' => $audit['entitas_id'] ?? null,
            'nilai_lama' => $audit['nilai_lama'] ?? null,
            'nilai_baru' => $audit['nilai_baru'] ?? null,
            'meta' => $meta ?: null,
            'waktu_aksi' => now(),
        ]);
    }
}
