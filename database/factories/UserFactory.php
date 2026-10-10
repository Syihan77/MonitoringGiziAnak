<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_ORANG_TUA,
            'wilayah_posyandu' => 'Posyandu Melati',
            'no_telp' => fake()->phoneNumber(),
            'consent_approved' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function kader(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_KADER,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
            'wilayah_posyandu' => null,
        ]);
    }
}
