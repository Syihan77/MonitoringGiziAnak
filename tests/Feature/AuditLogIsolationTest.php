<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kegagalan_sql_audit_tidak_membatalkan_transaksi_bisnis(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Uji isolasi savepoint membutuhkan PostgreSQL.');
        }

        $bisnisUser = User::factory()->create();
        $auditUser = (new User)->forceFill(['id' => (string) Str::uuid()]);
        $anakId = null;

        DB::transaction(function () use ($bisnisUser, $auditUser, &$anakId) {
            $anak = Anak::create([
                'user_id' => $bisnisUser->id,
                'nama' => 'Anak Audit',
                'tanggal_lahir' => '2024-01-15',
                'jenis_kelamin' => 'L',
            ]);
            $anakId = $anak->id;

            AuditLogger::log($auditUser, 'uji_isolasi_audit');
        });

        $this->assertDatabaseHas('anaks', ['id' => $anakId]);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
