<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'mobile' => fake()->unique()->numerify('09#########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'status' => 'active',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'is_admin' => true,
        ]);
    }

    public function client(): static
    {
        return $this->state(fn (): array => [
            'is_admin' => false,
            'status' => 'active',
        ]);
    }
}
