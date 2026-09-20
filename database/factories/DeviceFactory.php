<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'Pixel '.$this->faker->numberBetween(4, 9),
            'status' => 'inactive',
            'last_seen_at' => null,
            'fcm_token' => null,
        ];
    }

    /**
     * Seen just now, so the derived status is active.
     */
    public function online(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'last_seen_at' => now()->subSeconds($this->faker->numberBetween(5, 120)),
            'fcm_token' => 'fcm_'.Str::random(40),
        ]);
    }

    /**
     * Stale heartbeat: the row still says active but the UI must show offline.
     */
    public function stale(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'last_seen_at' => now()->subMinutes($this->faker->numberBetween(
                Device::ONLINE_THRESHOLD_MINUTES + 1,
                600
            )),
            'fcm_token' => 'fcm_'.Str::random(40),
        ]);
    }
}
