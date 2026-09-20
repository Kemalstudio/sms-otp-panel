<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_derived_from_last_seen_at(): void
    {
        $project = Project::factory()->create();

        $fresh = Device::factory()->for($project)->create([
            'status' => 'active',
            'last_seen_at' => now()->subMinutes(Device::ONLINE_THRESHOLD_MINUTES - 1),
        ]);

        // Column still says active, but the heartbeat is stale.
        $stale = Device::factory()->for($project)->create([
            'status' => 'active',
            'last_seen_at' => now()->subMinutes(Device::ONLINE_THRESHOLD_MINUTES + 1),
        ]);

        $never = Device::factory()->for($project)->create(['last_seen_at' => null]);

        $this->assertSame('active', $fresh->effective_status);
        $this->assertSame('inactive', $stale->effective_status);
        $this->assertSame('inactive', $never->effective_status);
        $this->assertSame(1, $project->onlineDevicesCount());
    }
}
