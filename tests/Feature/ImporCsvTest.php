<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use Database\Seeders\WhoStandardsSeeder;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImporCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([WilayahSeeder::class, WhoStandardsSeeder::class]);
    }

    private function asAdmin(): array
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');

        return [];
    }

    private function asOrtu(): array
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        return [];
    }

    private function csv(string $isi): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('impor.csv', $isi);
    }

    public function test_impor_anak_berhasil(): void
    {
        $pemilik = User::factory()->create();

        $res = $this->postJson('/api/v1/admin/impor-anak', [
            'file' => $this->csv(
                "nama,tanggal_lahir,jenis_kelamin,wilayah_posyandu,user_id,alamat\n"
                ."Budi,2024-01-15,L,Posyandu Melati,{$pemilik->id},Jl. Melati\n"
                ."Ani,2023-06-01,P,Posyandu Mawar,{$pemilik->id},\n"
            ),
        ], $this->asAdmin());

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_baris', 2)
            ->assertJsonPath('data.berhasil', 2)
            ->assertJsonPath('data.gagal', 0);

        $this->assertDatabaseHas('anaks', ['nama' => 'Budi']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'impor_anak']);
    }

    public function test_impor_anak_baris_rusak_dilaporkan_tanpa_gagalkan_batch(): void
    {
        $pemilik = User::factory()->create();

        $res = $this->postJson('/api/v1/admin/impor-anak', [
            'file' => $this->csv(
                "nama,tanggal_lahir,jenis_kelamin,wilayah_posyandu,user_id\n"
                ."Budi,2024-01-15,L,Posyandu Melati,{$pemilik->id}\n"
                .",2024-01-15,L,Posyandu Melati,{$pemilik->id}\n"
                ."Caca,2030-01-01,P,Posyandu Fiktif,00000000-0000-0000-0000-000000000000\n"
            ),
        ], $this->asAdmin());

        $res->assertOk()
            ->assertJsonPath('data.total_baris', 3)
            ->assertJsonPath('data.berhasil', 1)
            ->assertJsonPath('data.gagal', 2);

        $galat = $res->json('data.galat');
        $this->assertSame(3, $galat[0]['baris']);
        $this->assertSame(4, $galat[1]['baris']);
        $this->assertDatabaseHas('anaks', ['nama' => 'Budi']);
        $this->assertDatabaseMissing('anaks', ['nama' => 'Caca']);
    }

    public function test_impor_anak_header_tak_lengkap_400(): void
    {
        $this->postJson('/api/v1/admin/impor-anak', [
            'file' => $this->csv("nama,tanggal_lahir\nBudi,2024-01-15\n"),
        ], $this->asAdmin())
            ->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_impor_non_admin_403(): void
    {
        $this->postJson('/api/v1/admin/impor-anak', [
            'file' => $this->csv("nama,tanggal_lahir,jenis_kelamin,wilayah_posyandu,user_id\n"),
        ], $this->asOrtu())
            ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN_RESOURCE');
    }

    public function test_impor_ukur_hitung_z_otomatis(): void
    {
        $admin = User::factory()->admin()->create();
        $ortu = User::factory()->create();
        $anak = Anak::create([
            'user_id' => $ortu->id, 'nama' => 'Budi',
            'tanggal_lahir' => '2024-01-15', 'jenis_kelamin' => 'L',
        ]);

        $this->actingAs($admin, 'sanctum');
        $res = $this->postJson('/api/v1/admin/impor-ukur', [
            'file' => $this->csv(
                "anak_id,tanggal_ukur,berat_badan_kg,tinggi_badan_cm\n"
                ."{$anak->id},2026-01-15,12.5,85.2\n"
                ."00000000-0000-0000-0000-000000000000,2026-01-15,12.5,85.2\n"
            ),
        ]);

        $res->assertOk()
            ->assertJsonPath('data.berhasil', 1)
            ->assertJsonPath('data.gagal', 1);

        $this->assertDatabaseHas('pertumbuhans', [
            'anak_id' => $anak->id,
            'z_bb_u' => 0.21,
            'status_gizi_antropometri' => 'normal',
        ]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'impor_ukur']);
    }
}
