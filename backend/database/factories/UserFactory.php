<?php

namespace Database\Factories;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'username' => $username,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Roles::EMPLOYEE,
            'role_label' => Roles::label(Roles::EMPLOYEE),
            'employee_id' => 'EMP-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'department' => 'Administration',
            'position' => 'Employee',
            'can_manage_users' => false,
            'built_in' => false,
            'active' => true,
            'status' => 'active',
            'deleted' => false,
            'deleted_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'can_manage_users' => true,
            'built_in' => true,
            'position' => 'System Administrator',
        ]);
    }
}
