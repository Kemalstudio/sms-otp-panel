<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The scheduled sweeps that keep statuses honest between requests.
 */
class MaintenanceSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_timed_out_otps_are_expired_and_their_codes_forgotten(): void
    {
        $project = Project::factory()->create();

        $stale = OtpLog::factory()->for($project)->withCode('111111')->create([
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);
        $sentButUnverified = OtpLog::factory()->for($project)->withCode('222222')->create([
            'status' => 'sent',
            'expires_at' => now()->subHour(),
        ]);
        $live = OtpLog::factory()->for($project)->pending()->withCode('333333')->create();

        $this->artisan('otp:sweep-expired')->assertSuccessful();

        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertNull($stale->fresh()->code_encrypted);

        $this->assertSame('expired', $sentButUnverified->fresh()->status);

        // Still inside its window: untouched.
        $this->assertSame('pending', $live->fresh()->status);
        $this->assertSame('333333', $live->fresh()->code_encrypted);
    }

    public function test_the_sweep_scrubs_codes_left_on_resolved_logs(): void
    {
        $project = Project::factory()->create();

        $verified = OtpLog::factory()->for($project)->withCode('444444')->create([
            'status' => 'delivered',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->artisan('otp:sweep-expired')->assertSuccessful();

        $verified->refresh();
        $this->assertSame('delivered', $verified->status);
        $this->assertNull($verified->code_encrypted);
    }

    public function test_devices_that_stopped_reporting_in_are_marked_inactive(): void
    {
        $project = Project::factory()->create();

        $gone = Device::factory()->for($project)->stale()->create();
        $live = Device::factory()->for($project)->online()->create();
        $neverSeen = Device::factory()->for($project)->create(['status' => 'active', 'last_seen_at' => null]);

        $this->artisan('devices:sweep-stale')->assertSuccessful();

        $this->assertSame('inactive', $gone->fresh()->status);
        $this->assertSame('inactive', $neverSeen->fresh()->status);
        $this->assertSame('active', $live->fresh()->status);
    }
}
