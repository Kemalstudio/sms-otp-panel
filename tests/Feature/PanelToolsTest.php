<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use App\Models\User;
use App\Support\Stats\OtpTrend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Инструменты панели: поиск по логам, карточка кода, выгрузка, живые счётчики,
 * график и страница здоровья.
 */
class PanelToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_can_be_found_by_part_of_a_phone_number(): void
    {
        $project = Project::factory()->create();

        OtpLog::factory()->for($project)->create(['phone' => '+99365123456']);
        OtpLog::factory()->for($project)->create(['phone' => '+99361999888']);

        $this->actingAs($project->user)
            ->get(route('projects.logs.index', [$project, 'phone' => '123456']))
            ->assertOk()
            ->assertSee('99365123456', false)
            ->assertDontSee('99361999888', false);
    }

    public function test_the_search_ignores_how_the_number_was_typed(): void
    {
        // Оператор копирует номер откуда угодно: со скобками, пробелами, без кода.
        $project = Project::factory()->create();
        OtpLog::factory()->for($project)->create(['phone' => '+99365123456']);

        $this->actingAs($project->user)
            ->get(route('projects.logs.index', [$project, 'phone' => '+993 (65) 123-456']))
            ->assertOk()
            ->assertSee('99365123456', false);
    }

    public function test_one_code_has_a_page_of_its_own(): void
    {
        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->online()->create([
            'name' => 'Redmi на кассе',
            'phone_number' => '+99365000111',
        ]);
        $log = OtpLog::factory()->for($project)->create([
            'device_id' => $device->id,
            'status' => 'sent',
        ]);

        $this->actingAs($project->user)
            ->get(route('projects.logs.show', [$project, $log]))
            ->assertOk()
            ->assertSee('Код #'.$log->id)
            ->assertSee('Redmi на кассе')
            ->assertSee('+99365000111');
    }

    public function test_the_code_itself_never_appears_on_that_page(): void
    {
        $project = Project::factory()->create();
        $log = OtpLog::factory()->for($project)->create(['code_encrypted' => '123456']);

        $response = $this->actingAs($project->user)
            ->get(route('projects.logs.show', [$project, $log]))
            ->assertOk();

        $this->assertStringNotContainsString('123456', $response->getContent());
    }

    public function test_a_code_of_another_project_is_not_reachable(): void
    {
        $project = Project::factory()->create();
        $foreign = OtpLog::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.logs.show', [$project, $foreign]))
            ->assertNotFound();
    }

    public function test_logs_export_as_csv(): void
    {
        $project = Project::factory()->create();
        OtpLog::factory()->for($project)->create(['phone' => '+99365123456', 'status' => 'delivered']);

        $response = $this->actingAs($project->user)
            ->get(route('projects.logs.export', $project))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // BOM обязателен: без него Excel открывает UTF-8 как кракозябры.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('+99365123456', $csv);
        $this->assertStringContainsString('delivered', $csv);
    }

    public function test_the_export_respects_the_current_filter(): void
    {
        $project = Project::factory()->create();
        OtpLog::factory()->for($project)->create(['phone' => '+99365123456', 'status' => 'failed']);
        OtpLog::factory()->for($project)->create(['phone' => '+99361999888', 'status' => 'delivered']);

        $csv = $this->actingAs($project->user)
            ->get(route('projects.logs.export', [$project, 'status' => 'failed']))
            ->streamedContent();

        $this->assertStringContainsString('+99365123456', $csv);
        $this->assertStringNotContainsString('+99361999888', $csv);
    }

    public function test_the_pulse_endpoint_reports_live_numbers(): void
    {
        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->online()->create(['battery_level' => 64]);
        OtpLog::factory()->for($project)->create(['status' => 'failed', 'created_at' => now()]);

        $this->actingAs($project->user)
            ->getJson(route('projects.pulse', $project))
            ->assertOk()
            ->assertJsonPath('online', 1)
            ->assertJsonPath('sent_today', 1)
            ->assertJsonPath('failed_today', 1)
            ->assertJsonPath('devices.0.id', $device->id)
            ->assertJsonPath('devices.0.battery', 64);
    }

    public function test_a_stranger_gets_nothing_from_the_pulse(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('projects.pulse', $project))
            ->assertForbidden();
    }

    public function test_the_trend_buckets_codes_by_hour(): void
    {
        $project = Project::factory()->create();

        OtpLog::factory()->for($project)->count(2)->create([
            'status' => 'delivered',
            'created_at' => now()->subHours(2),
        ]);
        OtpLog::factory()->for($project)->create([
            'status' => 'failed',
            'created_at' => now()->subHours(2),
        ]);

        $hourly = OtpTrend::hourly($project);
        $bucket = $hourly->firstWhere('label', now()->subHours(2)->startOfHour()->format('H:i'));

        $this->assertSame(3, $bucket['total']);
        $this->assertSame(1, $bucket['failed']);
        $this->assertSame(2, $bucket['sent']);
        $this->assertSame(24, $hourly->count());
    }

    public function test_expired_codes_do_not_count_as_failures_on_the_chart(): void
    {
        // Код, который клиент просто не ввёл, — его решение, а не сбой шлюза.
        $project = Project::factory()->create();
        OtpLog::factory()->for($project)->create([
            'status' => 'expired',
            'created_at' => now()->subHour(),
        ]);

        $bucket = OtpTrend::hourly($project)->firstWhere('label', now()->subHour()->startOfHour()->format('H:i'));

        $this->assertSame(0, $bucket['failed']);
        $this->assertSame(1, $bucket['sent']);
    }

    public function test_the_health_page_reports_the_infrastructure(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('system.health'))
            ->assertOk()
            ->assertSee('Здоровье системы')
            ->assertSee('База данных')
            ->assertSee('Очередь')
            ->assertSee('Резервные копии');
    }

    public function test_the_docs_page_carries_this_gateway_address(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('system.docs'))
            ->assertOk()
            ->assertSee('/otp/send')
            ->assertSee('Idempotency-Key')
            ->assertSee(rtrim(config('app.url'), '/').'/api/v1', false);
    }

    public function test_guests_see_none_of_it(): void
    {
        foreach (['system.health', 'system.docs'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }
}
