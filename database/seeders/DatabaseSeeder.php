<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * A single demo account with one project, a few devices, a couple of API
     * keys and a week of OTP traffic — enough to see every screen populated.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
            ],
        );

        $project = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Demo Gateway'],
        );

        if ($project->devices()->doesntExist()) {
            $online = Device::factory()->online()->for($project)->create(['name' => 'Redmi Note 12 — офис']);
            Device::factory()->online()->for($project)->create(['name' => 'Samsung A54 — резерв']);
            Device::factory()->stale()->for($project)->create(['name' => 'Pixel 6 — склад']);
            Device::factory()->for($project)->create(['name' => 'Новый телефон (не привязан)']);
        } else {
            $online = $project->devices()->online()->first() ?? $project->devices()->first();
        }

        if ($project->apiKeys()->doesntExist()) {
            // Generated the same way the UI does it, so we can print the
            // plaintext once here and store only the hash.
            $liveKey = ApiKey::generateFor($project);
            $liveKey->forceFill(['last_used_at' => now()->subMinutes(12)])->save();

            ApiKey::factory()->for($project)->used()->create();
            ApiKey::factory()->for($project)->used()->revoked()->create();

            $this->command?->info('Demo API key (shown once, only the hash is stored): '.$liveKey->plainTextKey());
        }

        if ($project->otpLogs()->doesntExist()) {
            OtpLog::factory()->count(60)->for($project)->create([
                'device_id' => $online?->id,
            ]);

            OtpLog::factory()->count(24)->today()->for($project)->create([
                'device_id' => $online?->id,
            ]);
        }

        $this->command?->info('Demo login: demo@example.com / password');
    }
}
