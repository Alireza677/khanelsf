<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'display_name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'mobile' => fake()->optional()->numerify('09#########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'notes' => null,
            'status' => Customer::STATUS_ACTIVE,
        ];
    }
}
