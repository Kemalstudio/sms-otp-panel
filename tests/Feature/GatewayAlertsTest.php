<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use App\Notifications\GatewayAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Шлюз ломается тихо. Эти проверки — единственное, что замечает поломку
 * раньше клиента.
 */
class GatewayAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Http::fake();
    }

    public function test_a_pool_that_went_silent_raises_an_alert_once(): void
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->create([
            'status' => 'active',
            'last_seen_at' => now()->subHour(),
        ]);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $alert = Alert::query()->sole();
        $this->assertSame(Alert::TYPE_DEVICES_OFFLINE, $alert->type);
        $this->assertSame($project->id, $alert->project_id);

        Notification::assertSentTimes(GatewayAlertNotification::class, 1);

        // Проверка гоняется каждую минуту: второй прогон не должен писать
        // второе письмо, иначе владелец заведёт правило «в спам».
        $this->artisan('gateway:check-health')->assertSuccessful();

        $this->assertSame(1, Alert::count());
        Notification::assertSentTimes(GatewayAlertNotification::class, 1);
    }

    public function test_the_owner_is_the_one_who_gets_told(): void
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->create(['last_seen_at' => now()->subHour()]);

        $this->artisan('gateway:check-health');

        Notification::assertSentOnDemand(
            GatewayAlertNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $project->user->email,
        );
    }

    public function test_a_phone_coming_back_closes_the_alert(): void
    {
        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->create(['last_seen_at' => now()->subHour()]);

        $this->artisan('gateway:check-health');
        $this->assertSame('active', Alert::query()->sole()->status);

        $device->markSeen();
        $this->artisan('gateway:check-health');

        $alert = Alert::query()->sole();
        $this->assertSame('resolved', $alert->status);
        $this->assertNotNull($alert->resolved_at);

        // Одно письмо об аварии и одно о восстановлении.
        Notification::assertSentTimes(GatewayAlertNotification::class, 2);
    }

    public function test_a_project_without_phones_is_not_an_incident(): void
    {
        // Ненастроенный проект не сломан — будить за него некого.
        Project::factory()->create();

        $this->artisan('gateway:check-health')->assertSuccessful();

        $this->assertSame(0, Alert::count());
        Notification::assertNothingSent();
    }

    public function test_too_many_failed_sends_raise_an_alert(): void
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create();

        OtpLog::factory()->for($project)->count(6)->create([
            'status' => 'failed',
            'created_at' => now()->subMinutes(5),
        ]);
        OtpLog::factory()->for($project)->count(6)->create([
            'status' => 'delivered',
            'created_at' => now()->subMinutes(5),
        ]);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $alert = Alert::query()->where('type', Alert::TYPE_FAILURE_RATE)->sole();
        $this->assertStringContainsString('6 из 12', $alert->message);
    }

    public function test_codes_nobody_entered_are_not_counted_as_failures(): void
    {
        // `expired` — решение клиента не вводить код, а не поломка шлюза.
        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create();

        OtpLog::factory()->for($project)->count(12)->create([
            'status' => 'expired',
            'created_at' => now()->subMinutes(5),
        ]);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $this->assertSame(0, Alert::query()->where('type', Alert::TYPE_FAILURE_RATE)->count());
    }

    public function test_a_handful_of_errors_at_night_is_not_an_alert(): void
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create();

        OtpLog::factory()->for($project)->count(3)->create([
            'status' => 'failed',
            'created_at' => now()->subMinutes(5),
        ]);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $this->assertSame(0, Alert::query()->where('type', Alert::TYPE_FAILURE_RATE)->count());
    }

    public function test_a_stalled_queue_is_noticed(): void
    {
        // Заглянуть в очередь мы умеем только у database-драйвера; в тестах по
        // умолчанию стоит sync, где очереди как таковой нет.
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(30)->getTimestamp(),
            'created_at' => now()->subMinutes(30)->getTimestamp(),
        ]);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $alert = Alert::query()->where('type', Alert::TYPE_QUEUE_BACKLOG)->sole();

        $this->assertNull($alert->project_id);
        $this->assertStringContainsString('queue:work', $alert->message);
    }

    public function test_a_draining_queue_closes_the_alert(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(30)->getTimestamp(),
            'created_at' => now()->subMinutes(30)->getTimestamp(),
        ]);

        $this->artisan('gateway:check-health');
        DB::table('jobs')->delete();
        $this->artisan('gateway:check-health');

        $this->assertSame('resolved', Alert::query()->where('type', Alert::TYPE_QUEUE_BACKLOG)->sole()->status);
    }

    public function test_alerts_can_be_switched_off_entirely(): void
    {
        config(['gateway.alerts.enabled' => false]);

        $project = Project::factory()->create();
        Device::factory()->for($project)->create(['last_seen_at' => now()->subHour()]);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $this->assertSame(0, Alert::count());
    }

    public function test_telegram_is_used_when_configured(): void
    {
        config([
            'gateway.telegram.bot_token' => 'bot-token',
            'gateway.telegram.chat_id' => '42',
        ]);

        $project = Project::factory()->create();
        Device::factory()->for($project)->create(['last_seen_at' => now()->subHour()]);

        $this->artisan('gateway:check-health');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org/botbot-token/sendMessage')
            && $request['chat_id'] === '42'
            && str_contains($request['text'], 'Нет живых телефонов'));
    }

    public function test_a_broken_telegram_does_not_break_the_check(): void
    {
        // Упавший канал уведомлений не должен прятать саму аварию.
        config([
            'gateway.telegram.bot_token' => 'bot-token',
            'gateway.telegram.chat_id' => '42',
        ]);

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('no route'));

        $project = Project::factory()->create();
        Device::factory()->for($project)->create(['last_seen_at' => now()->subHour()]);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $this->assertSame(1, Alert::query()->active()->count());
    }

    public function test_a_queue_we_cannot_inspect_is_left_to_external_monitoring(): void
    {
        // redis-очередь мы не умеем читать и не будем врать, что всё хорошо:
        // проверка просто молчит, а следит за ней внешний мониторинг.
        config(['queue.default' => 'redis']);

        $this->artisan('gateway:check-health')->assertSuccessful();

        $this->assertSame(0, Alert::query()->where('type', Alert::TYPE_QUEUE_BACKLOG)->count());
    }

    public function test_the_panel_shows_open_alerts_of_this_project_only(): void
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->create(['last_seen_at' => now()->subHour()]);

        $other = Project::factory()->create();
        Device::factory()->for($other)->create(['last_seen_at' => now()->subHour()]);

        $this->artisan('gateway:check-health');

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Открытые аварии: 1')
            ->assertSee('Нет живых телефонов');
    }
}
