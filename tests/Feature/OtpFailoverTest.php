<?php

namespace Tests\Feature;

use App\Facades\Fcm;
use App\Jobs\SendOtpViaFcmJob;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * An unreachable handset must not sink the OTP while other phones are online.
 */
class OtpFailoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dead_handset_hands_the_otp_to_the_next_device(): void
    {
        // Faked so the retry is observed as a queued job rather than running
        // inline and burning through the whole pool in one call.
        Queue::fake();
        Fcm::fake()->shouldFail('The registration token is not registered.');

        $project = Project::factory()->create();
        $dead = Device::factory()->for($project)->online()->create([
            'fcm_token' => 'fcm-dead',
            'last_dispatched_at' => now()->subHour(),
        ]);
        $spare = Device::factory()->for($project)->online()->create([
            'fcm_token' => 'fcm-spare',
            'last_dispatched_at' => now(),
        ]);

        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create([
            'device_id' => $dead->id,
        ]);

        (new SendOtpViaFcmJob($otpLog->id, $dead->id))->handle();

        // Still live, now owned by the spare rather than failed outright.
        $otpLog->refresh();
        $this->assertSame('pending', $otpLog->status);
        $this->assertSame($spare->id, $otpLog->device_id);
        $this->assertSame('123456', $otpLog->code_encrypted);

        Queue::assertPushed(
            SendOtpViaFcmJob::class,
            fn (SendOtpViaFcmJob $job) => $job->deviceId === $spare->id
                && $job->triedDeviceIds === [$dead->id]
        );
    }

    public function test_the_otp_fails_once_every_device_has_been_tried(): void
    {
        $fcm = Fcm::fake()->shouldFail();

        $project = Project::factory()->create();
        $only = Device::factory()->for($project)->online()->create(['fcm_token' => 'fcm-only']);

        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create([
            'device_id' => $only->id,
        ]);

        (new SendOtpViaFcmJob($otpLog->id, $only->id))->handle();

        $otpLog->refresh();
        $this->assertSame('failed', $otpLog->status);
        // A resolved log keeps no reversible copy of the code.
        $this->assertNull($otpLog->code_encrypted);
    }

    public function test_a_device_is_never_tried_twice_for_one_otp(): void
    {
        Fcm::fake()->shouldFail();

        $project = Project::factory()->create();
        $first = Device::factory()->for($project)->online()->create(['fcm_token' => 'fcm-1']);
        $second = Device::factory()->for($project)->online()->create(['fcm_token' => 'fcm-2']);

        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create([
            'device_id' => $first->id,
        ]);

        // Arriving with both handsets already exhausted, there is nothing left.
        (new SendOtpViaFcmJob($otpLog->id, $second->id, [$first->id]))->handle();

        $this->assertSame('failed', $otpLog->fresh()->status);
    }

    public function test_an_otp_that_expired_in_the_queue_is_not_delivered(): void
    {
        $fcm = Fcm::fake();

        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->online()->create(['fcm_token' => 'fcm-1']);

        $otpLog = OtpLog::factory()->for($project)->withCode('123456')->create([
            'device_id' => $device->id,
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        (new SendOtpViaFcmJob($otpLog->id, $device->id))->handle();

        $fcm->assertNothingSent();
        $this->assertSame('expired', $otpLog->fresh()->status);
    }

    public function test_a_503_does_not_cost_the_caller_their_rate_limit_slot(): void
    {
        $project = Project::factory()->create();
        $key = ApiKey::generateFor($project);

        // No online device: the gateway, not the caller, is at fault.
        $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456'])
            ->assertStatus(503);

        $this->assertSame(0, RateLimiter::attempts("otp:send:key:{$key->id}"));

        // A device appears; the very next call must go through.
        Device::factory()->for($project)->online()->create();

        $this->withHeader('X-Api-Key', $key->plainTextKey())
            ->postJson('/api/v1/otp/send', ['phone' => '+99365123456'])
            ->assertStatus(202);
    }
}
