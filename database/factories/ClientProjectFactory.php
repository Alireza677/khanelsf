<?php

namespace Database\Factories;

use App\Models\ClientProject;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientProjectFactory extends Factory
{
    protected $model = ClientProject::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'type' => fake()->optional()->randomElement(['consulting', 'implementation', 'support']),
            'status' => ClientProject::STATUS_ACTIVE,
            'progress' => fake()->numberBetween(0, 100),
            'start_date' => fake()->optional()->date(),
            'end_date' => null,
        ];
    }
}
