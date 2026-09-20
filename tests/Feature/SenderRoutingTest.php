<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\PairingCode;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Телефон как отправитель: с какого номера уходит SMS и сколько штук в минуту
 * этот номер вывозит.
 */
class SenderRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_response_names_the_number_the_sms_goes_from(): void
    {
        [$project, $key] = $this->projectWithKey();

        Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000001',
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000001'])
            ->assertAccepted()
            ->assertJsonPath('from', '+99365000001');
    }

    public function test_a_caller_can_pin_the_sender_number(): void
    {
        [$project, $key] = $this->projectWithKey();

        // Первый телефон дольше всех не отправлял, поэтому round-robin
        // выбрал бы именно его — если бы отправителя не зафиксировали.
        Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000001',
            'last_dispatched_at' => now()->subDay(),
        ]);
        $pinned = Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000002',
            'last_dispatched_at' => now(),
        ]);

        $response = $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', [
                'phone' => '+99361000001',
                'from' => '+99365000002',
            ])
            ->assertAccepted()
            ->assertJsonPath('from', '+99365000002');

        $this->assertSame(
            $pinned->id,
            OtpLog::findOrFail($response->json('otp_id'))->device_id
        );
    }

    public function test_an_unknown_sender_number_is_rejected_as_a_caller_error(): void
    {
        [$project, $key] = $this->projectWithKey();

        Device::factory()->for($project)->online()->create(['phone_number' => '+99365000001']);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', [
                'phone' => '+99361000001',
                'from' => '+99365999999',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'unknown sender number');
    }

    public function test_a_phone_at_its_throughput_limit_stops_receiving_codes(): void
    {
        [$project, $key] = $this->projectWithKey();

        $device = Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000001',
            'throughput_per_minute' => 1,
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000001'])
            ->assertAccepted();

        // Другой номер получателя: лимит «один код на номер в минуту» не должен
        // маскировать проверку пропускной способности телефона.
        $response = $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000002'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'all devices are at their throughput limit');

        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertSame(1, $device->otpLogs()->count());
    }

    public function test_a_saturated_pool_does_not_spend_the_callers_per_number_budget(): void
    {
        [$project, $key] = $this->projectWithKey();

        Device::factory()->for($project)->online()->create(['throughput_per_minute' => 1]);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000001'])
            ->assertAccepted();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000002'])
            ->assertStatus(429);

        // Ёмкость освободилась — тот же получатель проходит сразу, хотя его
        // минутный слот сгорел бы, если бы отказ шлюза его списал.
        $this->travel(61)->seconds();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000002'])
            ->assertAccepted();
    }

    public function test_a_busy_phone_is_skipped_in_favour_of_a_free_one(): void
    {
        [$project, $key] = $this->projectWithKey();

        $busy = Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000001',
            'throughput_per_minute' => 1,
            'last_dispatched_at' => now()->subDay(),
        ]);
        $free = Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000002',
            'throughput_per_minute' => 5,
            'last_dispatched_at' => now(),
        ]);

        OtpLog::factory()->for($project)->pending()->create([
            'device_id' => $busy->id,
            'created_at' => now()->subSeconds(10),
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000001'])
            ->assertAccepted()
            ->assertJsonPath('from', $free->phone_number);
    }

    public function test_pairing_stores_the_sim_number_the_operator_typed(): void
    {
        $project = Project::factory()->create();
        $pairingCode = PairingCode::factory()->for($project)->create();

        $this->postJson('/api/v1/devices/pair', [
            'pairing_code' => $pairingCode->code,
            'device_name' => 'Samsung A54',
            'fcm_token' => 'fcm-token-abc',
            'phone_number' => '+993 65 000001',
        ])
            ->assertCreated()
            ->assertJsonPath('phone_number', '+99365000001');

        $this->assertSame('+99365000001', $project->devices()->sole()->phone_number);
    }

    public function test_heartbeat_reports_battery_and_learns_the_throughput(): void
    {
        $project = Project::factory()->create();
        $pairingCode = PairingCode::factory()->for($project)->create();

        $token = $this->postJson('/api/v1/devices/pair', [
            'pairing_code' => $pairingCode->code,
            'device_name' => 'Samsung A54',
            'fcm_token' => 'fcm-token-abc',
        ])->json('device_token');

        $device = $project->devices()->sole();
        $device->update(['throughput_per_minute' => 25]);

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/devices/heartbeat', ['battery_level' => 42])
            ->assertOk()
            ->assertJsonPath('throughput_per_minute', 25);

        $this->assertSame(42, $device->fresh()->battery_level);
    }

    /**
     * @return array{0: Project, 1: string}
     */
    private function projectWithKey(): array
    {
        // Доставка здесь не проверяется — только маршрутизация до телефона.
        Queue::fake();

        $project = Project::factory()->create();
        $key = ApiKey::generateFor($project);

        return [$project, $key->plainTextKey()];
    }
}
