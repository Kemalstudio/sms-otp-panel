<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Обзор проекта — единственная страница, отвечающая на вопрос «шлюз вообще
 * может сейчас доставить код», поэтому её вердикт проверяется отдельно.
 */
class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_project_says_the_gateway_is_not_ready(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Шлюз ещё не готов')
            ->assertSee('Нет ни одного подключённого телефона', false);
    }

    public function test_a_project_with_an_online_phone_and_a_key_is_ready(): void
    {
        $project = Project::factory()->create();

        Device::factory()->for($project)->create([
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        ApiKey::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Шлюз готов отправлять коды');
    }

    public function test_a_phone_that_stopped_reporting_in_is_called_out(): void
    {
        $project = Project::factory()->create();

        Device::factory()->for($project)->create([
            'status' => 'active',
            'last_seen_at' => now()->subHour(),
        ]);
        ApiKey::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Шлюз ещё не готов')
            ->assertSee('Ни один телефон не выходил на связь', false);
    }

    public function test_the_snippets_carry_this_gateway_address(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(rtrim(config('app.url'), '/').'/api/v1/otp/send', false);
    }

    public function test_the_checklist_marks_a_project_that_already_sent_codes(): void
    {
        $project = Project::factory()->create();
        OtpLog::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Шлюз уже отправлял коды', false);
    }
}
