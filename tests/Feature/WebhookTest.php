<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhookJob;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Вебхуки — единственный способ для клиентского бэкенда узнать судьбу кода.
 */
class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_status_change_queues_a_signed_event(): void
    {
        Queue::fake();

        $project = $this->projectWithWebhook();
        $device = Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000111',
        ]);
        $otpLog = OtpLog::factory()->for($project)->pending()->create(['device_id' => $device->id]);

        $otpLog->markStatus('sent');

        $delivery = $project->webhookDeliveries()->sole();

        $this->assertSame('otp.sent', $delivery->event);
        $this->assertSame('https://example.test/hooks', $delivery->url);
        $this->assertSame('pending', $delivery->status);

        $payload = json_decode($delivery->payload, true);

        $this->assertSame('otp.sent', $payload['event']);
        $this->assertSame($otpLog->id, $payload['data']['otp_id']);
        $this->assertSame('sent', $payload['data']['status']);
        $this->assertSame('+99365000111', $payload['data']['from']);

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_the_event_never_carries_the_code(): void
    {
        Queue::fake();

        $project = $this->projectWithWebhook();
        $otpLog = OtpLog::factory()->for($project)->pending()->create([
            'code_hash' => Hash::make('123456'),
            'code_encrypted' => '123456',
        ]);

        $otpLog->markStatus('sent');

        $payload = $project->webhookDeliveries()->sole()->payload;

        $this->assertStringNotContainsString('123456', $payload);
        $this->assertStringNotContainsString('code', $payload);
    }

    public function test_verifying_a_code_reports_it_as_verified(): void
    {
        Queue::fake();

        $project = $this->projectWithWebhook();
        $otpLog = OtpLog::factory()->for($project)->pending()->create();

        $otpLog->forceFill(['status' => 'delivered'])->save();

        $this->assertSame('otp.verified', $project->webhookDeliveries()->sole()->event);
    }

    public function test_the_sweeper_reports_expiry_even_though_it_updates_in_bulk(): void
    {
        Queue::fake();

        $project = $this->projectWithWebhook();
        OtpLog::factory()->for($project)->expired()->create();

        $this->artisan('otp:sweep-expired')->assertSuccessful();

        $delivery = $project->webhookDeliveries()->sole();

        $this->assertSame('otp.expired', $delivery->event);
        $this->assertSame('expired', json_decode($delivery->payload, true)['data']['status']);
    }

    public function test_a_project_without_a_webhook_queues_nothing(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        OtpLog::factory()->for($project)->pending()->create()->markStatus('sent');

        $this->assertSame(0, WebhookDelivery::count());
        Queue::assertNothingPushed();
    }

    public function test_the_receiver_can_verify_the_signature_as_documented(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $project = $this->projectWithWebhook();
        $delivery = $this->delivery($project);

        (new DeliverWebhookJob($delivery->id))->handle();

        Http::assertSent(function ($request) use ($project, $delivery) {
            $header = $request->header(WebhookSignature::HEADER)[0] ?? '';

            return WebhookSignature::verify($delivery->payload, $header, $project->webhook_secret)
                && ! WebhookSignature::verify($delivery->payload, $header, 'whsec_wrong')
                && $request->header('X-Gateway-Event')[0] === 'otp.sent';
        });

        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(200, $delivery->fresh()->response_status);
    }

    public function test_an_old_signature_is_not_accepted_twice_later(): void
    {
        $project = $this->projectWithWebhook();
        $payload = '{"event":"otp.sent"}';

        $stale = WebhookSignature::header($payload, $project->webhook_secret, time() - 3600);

        $this->assertFalse(WebhookSignature::verify($payload, $stale, $project->webhook_secret));
        $this->assertTrue(WebhookSignature::verify(
            $payload,
            WebhookSignature::header($payload, $project->webhook_secret),
            $project->webhook_secret,
        ));
    }

    public function test_a_rejected_delivery_is_retried_before_it_is_called_failed(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $project = $this->projectWithWebhook();
        $delivery = $this->delivery($project);

        // Пока попытки не исчерпаны, задача падает — очередь вернёт её с
        // паузой, а доставка остаётся pending, а не «провалена навсегда».
        try {
            (new DeliverWebhookJob($delivery->id))->handle();
            $this->fail('job should have thrown to trigger a retry');
        } catch (RuntimeException) {
            // ожидаемо
        }

        $delivery->refresh();

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(500, $delivery->response_status);
    }

    public function test_the_last_attempt_marks_the_delivery_failed(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $project = $this->projectWithWebhook();
        $delivery = $this->delivery($project);
        $delivery->forceFill(['attempts' => 4])->save();

        (new DeliverWebhookJob($delivery->id))->handle();

        $this->assertSame('failed', $delivery->fresh()->status);
    }

    public function test_an_unreachable_host_is_recorded_not_swallowed(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('dns failure'));

        $project = $this->projectWithWebhook();
        $delivery = $this->delivery($project);
        $delivery->forceFill(['attempts' => 4])->save();

        (new DeliverWebhookJob($delivery->id))->handle();

        $delivery->refresh();

        $this->assertSame('failed', $delivery->status);
        $this->assertStringContainsString('dns failure', $delivery->error);
    }

    public function test_a_delivered_event_is_not_sent_twice(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $project = $this->projectWithWebhook();
        $delivery = $this->delivery($project);
        $delivery->markDelivered(200);

        (new DeliverWebhookJob($delivery->id))->handle();

        Http::assertNothingSent();
    }

    private function projectWithWebhook(): Project
    {
        $project = Project::factory()->create(['webhook_url' => 'https://example.test/hooks']);
        $project->rotateWebhookSecret();

        return $project->fresh();
    }

    private function delivery(Project $project): WebhookDelivery
    {
        return $project->webhookDeliveries()->create([
            'event' => 'otp.sent',
            'url' => $project->webhook_url,
            'status' => 'pending',
            'payload' => '{"event":"otp.sent","data":{"otp_id":1}}',
        ]);
    }
}
