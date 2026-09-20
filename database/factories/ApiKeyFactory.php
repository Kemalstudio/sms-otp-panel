<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = ApiKey::PREFIX.Str::random(32);

        return [
            'project_id' => Project::factory(),
            'key_hash' => ApiKey::hashKey($plain),
            'key_prefix' => substr($plain, 0, strlen(ApiKey::PREFIX) + 8),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'last_used_at' => now()->subMinutes($this->faker->numberBetween(1, 2880)),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now()->subDays($this->faker->numberBetween(1, 20)),
        ]);
    }
}
