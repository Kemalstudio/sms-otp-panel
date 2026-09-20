<?php

namespace Tests\Feature;

use App\Facades\Fcm;
use App\Jobs\SendOtpViaFcmJob;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OtpSendApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_queues_a_job_for_the_active_device(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->online()->create();
        $key = ApiKey::generateFor($project);

        $response = $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456']);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->assertJsonStructure(['otp_id', 'status', 'expires_at']);

        $otpLog = OtpLog::findOrFail($response->json('otp_id'));

        $this->assertSame($project->id, $otpLog->project_id);
        $this->assertSame($device->id, $otpLog->device_id);
        $this->assertSame('+99365123456', $otpLog->phone);
        $this->assertSame('pending', $otpLog->status);
        $this->assertSame(0, $otpLog->attempts);
        $this->assertTrue($otpLog->expires_at->isFuture());

        // code_hash is bcrypt and matches the reversible copy kept for the SMS.
        $this->assertTrue(Hash::check($otpLog->code_encrypted, $otpLog->code_hash));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otpLog->code_encrypted);

        Queue::assertPushed(
            SendOtpViaFcmJob::class,
            fn (SendOtpViaFcmJob $job) => $job->otpLogId === $otpLog->id && $job->deviceId === $device->id
        );

        // Using the key stamps last_used_at.
        $this->assertNotNull($key->fresh()->last_used_at);
    }

    public function test_send_pushes_the_sms_text_to_the_device_over_fcm(): void
    {
        $fcm = Fcm::fake();

        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create(['fcm_token' => 'fcm-device-1']);
        $key = ApiKey::generateFor($project);

        // QUEUE_CONNECTION=sync in phpunit.xml, so the job runs inline here.
        $response = $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456']);

        $response->assertStatus(202);

        $fcm->assertSentCount(1);
        $fcm->assertSentTo('fcm-device-1', function (array $data) use ($response) {
            return $data['type'] === 'send_sms'
                && $data['otp_id'] === (string) $response->json('otp_id')
                && $data['phone'] === '+99365123456'
                && (bool) preg_match('/^Ваш код: \d{6}$/u', $data['message']);
        });

        // Once handed over, the reversible copy is dropped.
        $this->assertNull(OtpLog::findOrFail($response->json('otp_id'))->code_encrypted);
    }

    public function test_send_marks_the_log_failed_when_fcm_rejects_the_message(): void
    {
        Fcm::fake()->shouldFail();

        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create();
        $key = ApiKey::generateFor($project);

        $response = $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456']);

        $response->assertStatus(202);

        $this->assertSame('failed', OtpLog::findOrFail($response->json('otp_id'))->status);
    }

    public function test_send_is_rejected_without_a_valid_api_key(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create();
        $revoked = ApiKey::generateFor($project);
        $revoked->revoke();

        $this->postJson('/api/v1/otp/send', ['phone' => '+99365123456'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'missing api key');

        $this->withHeader('X-Api-Key', 'sk_live_totally_made_up')
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'invalid api key');

        $this->withHeader('X-Api-Key', $revoked->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'invalid api key');

        $this->assertSame(0, OtpLog::count());
        Queue::assertNothingPushed();
    }

    public function test_send_validates_the_phone_format(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create();
        $key = ApiKey::generateFor($project);

        foreach (['+9936512345', '+993651234567', '99365123456', '+79001234567', 'not-a-phone'] as $phone) {
            $this->withHeader('X-Api-Key', $key->plainTextKey())
                ->postJson('/api/v1/otp/send', ['phone' => $phone])
                ->assertStatus(422)
                ->assertJsonValidationErrors('phone');
        }

        $this->assertSame(0, OtpLog::count());
    }

    public function test_send_returns_503_when_no_device_is_online(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        // Reported in too long ago, so it is not dispatchable.
        Device::factory()->for($project)->stale()->create();
        $key = ApiKey::generateFor($project);

        $response = $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456']);

        $response->assertStatus(503)
            ->assertJsonPath('message', 'no active device available')
            ->assertJsonPath('status', 'failed');

        $otpLog = OtpLog::findOrFail($response->json('otp_id'));

        $this->assertSame('failed', $otpLog->status);
        $this->assertNull($otpLog->device_id);
        $this->assertNull($otpLog->code_encrypted);

        Queue::assertNothingPushed();
    }

    public function test_devices_take_turns_round_robin(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $first = Device::factory()->for($project)->online()->create([
            'last_dispatched_at' => now()->subMinutes(10),
        ]);
        $second = Device::factory()->for($project)->online()->create([
            'last_dispatched_at' => now()->subMinutes(30),
        ]);
        $key = ApiKey::generateFor($project);

        // Least recently used goes first.
        $this->assertSame($second->id, $project->nextDispatchableDevice()->id);

        $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456'])
            ->assertStatus(202);

        $this->assertTrue($second->fresh()->last_dispatched_at->isAfter(now()->subMinute()));
        $this->assertSame($first->id, $project->fresh()->nextDispatchableDevice()->id);
    }

    public function test_only_devices_of_the_owning_project_are_used(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        Device::factory()->online()->create(); // belongs to some other project
        $key = ApiKey::generateFor($project);

        $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456'])
            ->assertStatus(503);
    }
}
