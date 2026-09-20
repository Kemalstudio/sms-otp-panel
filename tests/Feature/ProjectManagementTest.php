<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_lists_projects_with_their_counters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Gateway One']);

        Device::factory()->online()->for($project)->create();
        Device::factory()->stale()->for($project)->create();
        $project->otpLogs()->saveMany(
            OtpLog::factory()->count(3)->today()->make()
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Gateway One')
            ->assertViewHas('projects', function ($projects) {
                $project = $projects->first();

                return $project->online_devices_count === 1
                    && $project->otp_today_count === 3;
            });
    }

    public function test_a_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), ['name' => 'New Gateway'])
            // Первое, что нужно новому проекту, — живой телефон, поэтому сразу
            // страница устройств с открытым QR привязки.
            ->assertRedirect(route('projects.devices.index', $user->projects()->sole()))
            ->assertSessionHas('show_pairing');

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'New Gateway',
        ]);
    }

    public function test_a_new_project_gets_a_usable_pairing_code_right_away(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), ['name' => 'New Gateway']);

        $code = $user->projects()->sole()->pairingCodes()->sole();

        $this->assertTrue($code->isUsable());
        $this->assertSame(6, mb_strlen($code->code));
    }

    public function test_the_devices_page_shows_that_code_as_a_qr(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), ['name' => 'New Gateway']);

        $project = $user->projects()->sole();
        $code = $project->pairingCodes()->sole();

        $this->actingAs($user)
            ->get(route('projects.devices.index', $project))
            ->assertOk()
            ->assertSee($code->code)
            // QR рисуется инлайновым SVG, а не картинкой по ссылке.
            ->assertSee('<svg', false);
    }

    public function test_a_project_name_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('projects.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_a_device_is_registered_offline_until_it_reports_in(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->post(route('projects.devices.store', $project), ['name' => 'Pixel 7'])
            ->assertRedirect(route('projects.devices.index', $project));

        $device = $project->devices()->sole();

        $this->assertSame('Pixel 7', $device->name);
        $this->assertNull($device->last_seen_at);
        $this->assertSame('inactive', $device->effective_status);
    }

    public function test_a_device_cannot_be_added_to_someone_elses_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('projects.devices.store', $project), ['name' => 'Pixel 7'])
            ->assertForbidden();

        $this->assertSame(0, $project->devices()->count());
    }
}
