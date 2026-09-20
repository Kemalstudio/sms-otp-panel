<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\OtpLog;
use App\Models\PairingCode;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceReportingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_brings_a_stale_device_back_online(): void
    {
        [$device, $token] = $this->pairedDevice([
            'status' => 'inactive',
            'last_seen_at' => now()->subHour(),
        ]);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/heartbeat')
            ->assertOk()
            ->assertJsonPath('device_id', $device->id)
            ->assertJsonPath('status', 'active');

        $device->refresh();

        $this->assertSame('active', $device->status);
        $this->assertTrue($device->isOnline());
    }

    public function test_heartbeat_adopts_a_rotated_fcm_token(): void
    {
        [$device, $token] = $this->pairedDevice(['fcm_token' => 'old-address']);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/heartbeat', ['fcm_token' => 'rotated-address'])
            ->assertOk();

        $this->assertSame('rotated-address', $device->fresh()->fcm_token);
    }

    public function test_heartbeat_without_a_token_leaves_the_stored_one_alone(): void
    {
        [$device, $token] = $this->pairedDevice(['fcm_token' => 'still-good']);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/heartbeat')
            ->assertOk();

        $this->assertSame('still-good', $device->fresh()->fcm_token);
    }

    public function test_device_endpoints_reject_a_bad_or_missing_token(): void
    {
        $this->postJson('/api/v1/devices/heartbeat')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'missing device token');

        $this->withHeader('X-Device-Token', 'not-a-real-token')
            ->postJson('/api/v1/devices/heartbeat')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'invalid device token');
    }

    public function test_device_reports_the_sms_it_sent(): void
    {
        [$device, $token] = $this->pairedDevice();
        $otpLog = OtpLog::factory()->for($device->project)->pending()->create(['device_id' => $device->id]);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/report-status', ['otp_id' => $otpLog->id, 'status' => 'sent'])
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $this->assertSame('sent', $otpLog->fresh()->status);
    }

    public function test_device_reports_a_failed_sms(): void
    {
        [$device, $token] = $this->pairedDevice();
        $otpLog = OtpLog::factory()->for($device->project)->pending()->create(['device_id' => $device->id]);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/report-status', ['otp_id' => $otpLog->id, 'status' => 'failed'])
            ->assertOk();

        $this->assertSame('failed', $otpLog->fresh()->status);
    }

    public function test_a_device_cannot_report_on_an_otp_it_was_not_given(): void
    {
        [, $token] = $this->pairedDevice();
        $foreign = OtpLog::factory()->pending()->create();

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/report-status', ['otp_id' => $foreign->id, 'status' => 'sent'])
            ->assertNotFound();

        $this->assertSame('pending', $foreign->fresh()->status);
    }

    public function test_a_late_report_does_not_undo_a_successful_verification(): void
    {
        [$device, $token] = $this->pairedDevice();
        $otpLog = OtpLog::factory()->for($device->project)->create([
            'device_id' => $device->id,
            'status' => 'delivered',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/report-status', ['otp_id' => $otpLog->id, 'status' => 'sent'])
            ->assertOk()
            ->assertJsonPath('status', 'delivered');

        $this->assertSame('delivered', $otpLog->fresh()->status);
    }

    public function test_report_status_only_accepts_sent_or_failed(): void
    {
        [$device, $token] = $this->pairedDevice();
        $otpLog = OtpLog::factory()->for($device->project)->pending()->create(['device_id' => $device->id]);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/report-status', ['otp_id' => $otpLog->id, 'status' => 'delivered'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * Pairs a phone the way the real handshake does, so the test exercises
     * the token that pairing actually hands out.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Device, 1: string}
     */
    private function pairedDevice(array $attributes = []): array
    {
        $project = Project::factory()->create();
        $pairingCode = PairingCode::factory()->for($project)->create();

        $response = $this->postJson('/api/v1/devices/pair', [
            'pairing_code' => $pairingCode->code,
            'device_name' => 'Samsung A54',
            'fcm_token' => 'fcm-token-abc',
        ])->assertCreated();

        $device = Device::findOrFail($response->json('device_id'));

        if ($attributes !== []) {
            $device->forceFill($attributes)->save();
        }

        return [$device, $response->json('device_token')];
    }
}
