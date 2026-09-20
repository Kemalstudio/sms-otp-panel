<?php

namespace Database\Factories;

use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<\App\Models\OtpLog>
 */
class OtpLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = Carbon::instance($this->faker->dateTimeBetween('-6 days', 'now'));

        return [
            'project_id' => Project::factory(),
            'device_id' => null,
            'phone' => '+9936'.$this->faker->numberBetween(1, 5).$this->faker->numerify('######'),
            // The OTP itself is never stored, only its hash.
            'code_hash' => hash('sha256', (string) $this->faker->numberBetween(100000, 999999)),
            'status' => $this->faker->randomElement(OtpLog::STATUSES),
            'expires_at' => $createdAt->copy()->addMinutes(5),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * A log whose code is known to the test, hashed exactly the way
     * /api/v1/otp/send hashes it.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn () => [
            'code_hash' => Hash::make($code),
            'code_encrypted' => $code,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function today(): static
    {
        return $this->state(function () {
            $minutesIntoDay = (int) Carbon::today()->diffInMinutes(now());
            $createdAt = Carbon::today()->addMinutes($this->faker->numberBetween(0, max(0, $minutesIntoDay)));

            return [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'expires_at' => $createdAt->copy()->addMinutes(5),
            ];
        });
    }
}
