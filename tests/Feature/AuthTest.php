<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Master wilayah untuk validasi exists pada registrasi.
        $this->seed(WilayahSeeder::class);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nama' => 'Ibu Ratna',
            'email' => 'ratna@example.com',
            'password' => 'Rahasia123',
            'role' => 'orang_tua',
            'wilayah_posyandu' => 'Posyandu Melati',
            'no_telp' => '081234567890',
            'consent_approved' => true,
        ], $overrides);
    }

    public function test_register_berhasil(): void
    {
        $res = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'orang_tua')
            ->assertJsonStructure(['data' => ['user_id', 'role']]);

        $this->assertDatabaseHas('users', ['email' => 'ratna@example.com']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'register']);
    }

    // UT-01: consent false ditolak 400 CONSENT_REQUIRED.
    public function test_register_consent_false_ditolak(): void
    {
        $res = $this->postJson('/api/v1/auth/register', $this->validPayload(['consent_approved' => false]));

        $res->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'CONSENT_REQUIRED');

        $this->assertDatabaseMissing('users', ['email' => 'ratna@example.com']);
    }

    // UT-02: email duplikat ditolak dengan pesan "email sudah digunakan".
    public function test_register_email_duplikat_ditolak(): void
    {
        User::factory()->create(['email' => 'ratna@example.com']);

        $res = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $res->assertBadRequest()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'Data yang dikirim tidak valid.')
            ->assertJsonPath('errors.email.0', 'Email sudah digunakan.');
    }

    // Validasi gagal -> 400 VALIDATION_ERROR (bukan 422).
    public function test_register_password_lemah_ditolak_400(): void
    {
        $res = $this->postJson('/api/v1/auth/register', $this->validPayload(['password' => 'lemah']));

        $res->assertBadRequest()->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    // SKPL §5.4.3: wilayah wajib apabila role=kader; orang tua boleh kosong.
    public function test_register_wilayah_hanya_wajib_kader(): void
    {
        $payload = $this->validPayload();
        unset($payload['wilayah_posyandu']);

        // Orang tua tanpa wilayah -> lolos.
        $this->postJson('/api/v1/auth/register', $payload)->assertCreated();

        // Kader tanpa wilayah -> ditolak 400.
        $kader = $this->validPayload(['email' => 'kader@example.com', 'role' => 'kader']);
        unset($kader['wilayah_posyandu']);

        $this->postJson('/api/v1/auth/register', $kader)
            ->assertBadRequest()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');

        // Kader dengan wilayah -> lolos.
        $this->postJson('/api/v1/auth/register', $this->validPayload(['email' => 'kader2@example.com', 'role' => 'kader']))
            ->assertCreated();
    }

    public function test_register_wilayah_tidak_terdaftar_ditolak(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload(['wilayah_posyandu' => 'Posyandu Fiktif']))
            ->assertBadRequest()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_login_berhasil_terbitkan_token(): void
    {
        User::factory()->create(['email' => 'ratna@example.com', 'password' => 'Rahasia123']);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'ratna@example.com',
            'password' => 'Rahasia123',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'role', 'user_id']]);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'login']);
    }

    // UT-03: password salah -> 401 INVALID_CREDENTIALS tanpa token.
    public function test_login_password_salah_ditolak(): void
    {
        User::factory()->create(['email' => 'ratna@example.com', 'password' => 'Rahasia123']);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'ratna@example.com',
            'password' => 'Salah1234',
        ]);

        $res->assertUnauthorized()
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS')
            ->assertJsonMissingPath('data.token');
    }

    public function test_me_dan_logout(): void
    {
        $user = User::factory()->create(['email' => 'ratna@example.com']);
        $token = $user->createToken('api', ['*'], now()->addHours(24))->plainTextToken;

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.email', 'ratna@example.com');

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // Token kedaluwarsa -> 401 TOKEN_EXPIRED.
    public function test_token_kedaluarsa_401_token_expired(): void
    {
        $user = User::factory()->create(['email' => 'ratna@example.com']);
        $token = $user->createToken('api', ['*'], now()->subHour())->plainTextToken;

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'TOKEN_EXPIRED');
    }

    // Tanpa token -> 401 UNAUTHENTICATED.
    public function test_tanpa_token_401_unauthenticated(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }
}
