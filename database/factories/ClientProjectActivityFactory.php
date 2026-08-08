<?php

namespace Database\Factories;

use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientProjectActivityFactory extends Factory
{
    protected $model = ClientProjectActivity::class;

    public function definition(): array
    {
        return [
            'client_project_id' => ClientProject::factory(),
            'performed_by' => null,
            'activity_date' => now()->toDateString(),
            'started_at' => null,
            'ended_at' => null,
            'duration_minutes' => fake()->numberBetween(15, 240),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'internal_notes' => fake()->optional()->sentence(),
            'visibility' => ClientProjectActivity::VISIBILITY_INTERNAL,
            'status' => ClientProjectActivity::STATUS_DRAFT,
        ];
    }

    public function publishedForClient(): static
    {
        return $this->state(fn (): array => [
            'visibility' => ClientProjectActivity::VISIBILITY_CLIENT,
            'status' => ClientProjectActivity::STATUS_PUBLISHED,
        ]);
    }
}
