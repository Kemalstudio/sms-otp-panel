<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.devices.index', $project))
            ->assertRedirect(route('login'));
    }

    public function test_a_user_cannot_open_someone_elses_project(): void
    {
        $project = Project::factory()->create();
        $intruder = User::factory()->create();

        foreach (['show', 'devices.index', 'api-keys.index', 'logs.index'] as $route) {
            $this->actingAs($intruder)
                ->get(route('projects.'.$route, $project))
                ->assertForbidden();
        }
    }

    public function test_the_owner_can_open_the_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.devices.index', $project))
            ->assertOk();
    }

    public function test_a_key_cannot_be_revoked_through_another_project(): void
    {
        $victim = Project::factory()->create();
        $key = ApiKey::factory()->for($victim)->create();

        $attackerProject = Project::factory()->create();

        $this->actingAs($attackerProject->user)
            ->delete(route('projects.api-keys.destroy', [$attackerProject, $key]))
            ->assertNotFound();

        $this->assertNull($key->fresh()->revoked_at);
    }
}
