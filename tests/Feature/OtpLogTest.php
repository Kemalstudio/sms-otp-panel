<?php

namespace Tests\Feature;

use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_is_masked_for_display(): void
    {
        $log = new OtpLog(['phone' => '+99365123456']);

        $this->assertSame('+993 XX XXX 3456', $log->masked_phone);
    }

    public function test_logs_can_be_filtered_by_status(): void
    {
        $project = Project::factory()->create();
        OtpLog::factory()->count(3)->for($project)->create(['status' => 'delivered']);
        OtpLog::factory()->count(2)->for($project)->create(['status' => 'failed']);

        $this->actingAs($project->user)
            ->get(route('projects.logs.index', [$project, 'status' => 'failed']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);
    }

    public function test_an_unknown_status_filter_is_rejected(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.logs.index', [$project, 'status' => 'bogus']))
            ->assertSessionHasErrors('status');
    }
}
