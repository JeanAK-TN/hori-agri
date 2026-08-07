<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Cooperative;
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
            'cooperative_id' => Cooperative::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('9#######'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Technician,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user has an optional email address.
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'cooperative_id' => null,
            'role' => Role::SuperAdmin,
        ]);
    }

    public function coopAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::CoopAdmin,
        ]);
    }

    public function technician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Technician,
        ]);
    }

    public function farmer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Farmer,
        ]);
    }
}
