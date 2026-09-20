<?php

namespace Database\Factories;

use App\Models\PairingCode;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\PairingCode>
 */
class PairingCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => Str::upper(Str::random(6)),
            'expires_at' => now()->addMinutes(PairingCode::LIFETIME_MINUTES),
            'used_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinutes($this->faker->numberBetween(1, 120)),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'used_at' => now()->subMinutes($this->faker->numberBetween(1, 5)),
        ]);
    }
}
