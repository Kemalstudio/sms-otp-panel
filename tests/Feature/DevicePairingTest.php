<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\PairingCode;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_generate_a_pairing_code_and_see_its_qr(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('projects.pairing-codes.store', $project))
            ->assertRedirect(route('projects.devices.index', $project));

        $pairingCode = $project->pairingCodes()->sole();

        $this->assertSame(6, strlen($pairingCode->code));
        $this->assertNull($pairingCode->used_at);
        $this->assertTrue($pairingCode->expires_at->between(
            now()->addMinutes(PairingCode::LIFETIME_MINUTES)->subSeconds(10),
            now()->addMinutes(PairingCode::LIFETIME_MINUTES)->addSeconds(10),
        ));

        $response = $this->actingAs($user)->get(route('projects.devices.index', $project));

        $response->assertOk()
            ->assertSee($pairingCode->code)
            // The QR carries the JSON handshake payload, rendered inline as SVG.
            ->assertSee('<svg', false);

        $this->assertSame(
            ['pairing_code' => $pairingCode->code, 'api_url' => rtrim(config('app.url'), '/')],
            json_decode($pairingCode->qrPayload(), true),
        );
    }

    public function test_a_stranger_cannot_generate_a_pairing_code(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('projects.pairing-codes.store', $project))
            ->assertForbidden();

        $this->assertSame(0, $project->pairingCodes()->count());
    }

    public function test_expired_code_page_asks_for_a_new_one(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        PairingCode::factory()->for($project)->expired()->create();

        $this->actingAs($user)
            ->get(route('projects.devices.index', $project))
            ->assertOk()
            ->assertSee('Код устарел, сгенерируйте новый.');
    }

    public function test_device_pairs_with_a_valid_code_and_receives_a_token_once(): void
    {
        $project = Project::factory()->create();
        $pairingCode = PairingCode::factory()->for($project)->create();

        $response = $this->postJson('/api/v1/devices/pair', [
            'pairing_code' => $pairingCode->code,
            'device_name' => 'Samsung A54',
            'fcm_token' => 'fcm-token-abc',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['device_id', 'device_token'])
            ->assertJsonPath('device_name', 'Samsung A54');

        $token = $response->json('device_token');
        $this->assertSame(64, strlen($token));

        $device = Device::findOrFail($response->json('device_id'));

        $this->assertSame($project->id, $device->project_id);
        $this->assertSame('active', $device->status);
        $this->assertSame('fcm-token-abc', $device->fcm_token);
        $this->assertNotNull($device->last_seen_at);
        // Only the hash is stored, never the token itself.
        $this->assertSame(Device::hashToken($token), $device->token_hash);
        $this->assertDatabaseMissing('devices', ['token_hash' => $token]);

        $this->assertNotNull($pairingCode->fresh()->used_at);
    }

    public function test_a_pairing_code_cannot_be_claimed_twice(): void
    {
        $pairingCode = PairingCode::factory()->create();

        $payload = [
            'pairing_code' => $pairingCode->code,
            'device_name' => 'Samsung A54',
            'fcm_token' => 'fcm-token-abc',
        ];

        $this->postJson('/api/v1/devices/pair', $payload)->assertCreated();

        $this->postJson('/api/v1/devices/pair', $payload)
            ->assertNotFound()
            ->assertJsonPath('message', 'invalid or expired code');

        $this->assertSame(1, Device::count());
    }

    public function test_expired_and_unknown_codes_are_rejected(): void
    {
        $expired = PairingCode::factory()->expired()->create();

        $this->postJson('/api/v1/devices/pair', [
            'pairing_code' => $expired->code,
            'device_name' => 'Samsung A54',
            'fcm_token' => 'fcm-token-abc',
        ])->assertNotFound()->assertJsonPath('message', 'invalid or expired code');

        $this->postJson('/api/v1/devices/pair', [
            'pairing_code' => 'ZZZZZZ',
            'device_name' => 'Samsung A54',
            'fcm_token' => 'fcm-token-abc',
        ])->assertNotFound();

        $this->assertSame(0, Device::count());
    }

    public function test_pairing_requires_a_name_and_an_fcm_token(): void
    {
        $pairingCode = PairingCode::factory()->create();

        $this->postJson('/api/v1/devices/pair', ['pairing_code' => $pairingCode->code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['device_name', 'fcm_token']);

        $this->assertNull($pairingCode->fresh()->used_at);
    }
}
