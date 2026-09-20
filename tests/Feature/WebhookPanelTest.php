<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Управление вебхуками в панели.
 */
class WebhookPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_an_address_also_issues_a_signing_secret(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->patch(route('projects.webhooks.update', $project), [
                'webhook_url' => 'https://api.example.test/hooks/otp',
            ])
            ->assertRedirect(route('projects.webhooks.index', $project));

        $project->refresh();

        // Вебхук без подписи бесполезен: получатель не отличит наш запрос от чужого.
        $this->assertSame('https://api.example.test/hooks/otp', $project->webhook_url);
        $this->assertStringStartsWith('whsec_', $project->webhook_secret);
        $this->assertTrue($project->hasWebhook());
    }

    public function test_clearing_the_address_switches_webhooks_off(): void
    {
        $project = Project::factory()->create(['webhook_url' => 'https://api.example.test/hooks']);
        $project->rotateWebhookSecret();

        $this->actingAs($project->user)
            ->patch(route('projects.webhooks.update', $project), ['webhook_url' => ''])
            ->assertRedirect();

        $this->assertFalse($project->fresh()->hasWebhook());
    }

    public function test_a_malformed_address_is_rejected(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->patch(route('projects.webhooks.update', $project), ['webhook_url' => 'not-a-url'])
            ->assertSessionHasErrors('webhook_url');

        $this->assertNull($project->fresh()->webhook_url);
    }

    public function test_rotating_the_secret_invalidates_the_old_one(): void
    {
        $project = Project::factory()->create(['webhook_url' => 'https://api.example.test/hooks']);
        $old = $project->rotateWebhookSecret();

        $this->actingAs($project->user)
            ->post(route('projects.webhooks.rotate', $project))
            ->assertRedirect();

        $this->assertNotSame($old, $project->fresh()->webhook_secret);
    }

    public function test_the_test_event_reaches_the_delivery_log(): void
    {
        Queue::fake();

        $project = Project::factory()->create(['webhook_url' => 'https://api.example.test/hooks']);
        $project->rotateWebhookSecret();

        $this->actingAs($project->user)
            ->post(route('projects.webhooks.test', $project))
            ->assertRedirect();

        $this->assertSame('webhook.test', $project->webhookDeliveries()->sole()->event);
    }

    public function test_a_test_event_needs_an_address_first(): void
    {
        Queue::fake();

        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->post(route('projects.webhooks.test', $project))
            ->assertRedirect();

        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_the_page_shows_the_deliveries_of_this_project_only(): void
    {
        $project = Project::factory()->create(['webhook_url' => 'https://api.example.test/hooks']);
        $project->rotateWebhookSecret();

        $project->webhookDeliveries()->create([
            'event' => 'otp.sent',
            'url' => $project->webhook_url,
            'status' => 'delivered',
            'payload' => '{}',
        ]);

        $other = Project::factory()->create(['webhook_url' => 'https://elsewhere.test/hooks']);
        $other->webhookDeliveries()->create([
            'event' => 'otp.expired',
            'url' => $other->webhook_url,
            'status' => 'failed',
            'error' => 'чужая ошибка доставки',
            'payload' => '{}',
        ]);

        // Имена событий перечислены в боковой справке, поэтому изоляция
        // проверяется по тому, что уникально для чужой доставки.
        $this->actingAs($project->user)
            ->get(route('projects.webhooks.index', $project))
            ->assertOk()
            ->assertSee('otp.sent')
            ->assertDontSee('чужая ошибка доставки');
    }

    public function test_a_stranger_cannot_read_or_change_the_secret(): void
    {
        $project = Project::factory()->create(['webhook_url' => 'https://api.example.test/hooks']);
        $project->rotateWebhookSecret();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('projects.webhooks.index', $project))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->patch(route('projects.webhooks.update', $project), [
                'webhook_url' => 'https://attacker.test/hooks',
            ])
            ->assertForbidden();

        $this->assertSame('https://api.example.test/hooks', $project->fresh()->webhook_url);
    }
}
