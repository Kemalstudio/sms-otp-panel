<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * `GET /otp/{id}` — запасной путь к вебхукам: если приёмник лежал дольше, чем
 * живут ретраи, судьбу кода больше узнать неоткуда.
 */
class OtpStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_the_state_of_a_code(): void
    {
        [$project, $key] = $this->projectWithKey();

        $device = Device::factory()->for($project)->online()->create([
            'phone_number' => '+99365000111',
        ]);
        $otpLog = OtpLog::factory()->for($project)->pending()->create([
            'device_id' => $device->id,
            'phone' => '+99361000001',
            'attempts' => 2,
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/otp/'.$otpLog->id)
            ->assertOk()
            ->assertJsonPath('otp_id', $otpLog->id)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('phone', '+99361000001')
            ->assertJsonPath('from', '+99365000111')
            ->assertJsonPath('attempts', 2)
            ->assertJsonPath('attempts_left', OtpLog::MAX_VERIFY_ATTEMPTS - 2);
    }

    public function test_it_never_hands_back_the_code(): void
    {
        [$project, $key] = $this->projectWithKey();

        $otpLog = OtpLog::factory()->for($project)->pending()->create([
            'code_hash' => Hash::make('123456'),
            'code_encrypted' => '123456',
        ]);

        $response = $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/otp/'.$otpLog->id)
            ->assertOk();

        $this->assertStringNotContainsString('123456', $response->getContent());
        $response->assertJsonMissingPath('code_hash');
        $response->assertJsonMissingPath('code_encrypted');
    }

    public function test_a_code_of_another_project_is_simply_not_found(): void
    {
        // Не «нет доступа»: существование чужих идентификаторов — тоже
        // информация, и раскрывать её незачем.
        [, $key] = $this->projectWithKey();
        $foreign = OtpLog::factory()->create();

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/otp/'.$foreign->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'otp not found');
    }

    public function test_it_needs_a_key_like_every_other_otp_route(): void
    {
        $otpLog = OtpLog::factory()->create();

        $this->getJson('/api/v1/otp/'.$otpLog->id)->assertUnauthorized();
    }

    public function test_the_status_follows_the_code_through_its_life(): void
    {
        [$project, $key] = $this->projectWithKey();

        $otpLog = OtpLog::factory()->for($project)->pending()->create();

        $otpLog->markStatus('sent');
        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/otp/'.$otpLog->id)
            ->assertJsonPath('status', 'sent');

        $otpLog->markStatus('delivered');
        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/otp/'.$otpLog->id)
            ->assertJsonPath('status', 'delivered');
    }

    public function test_an_idempotency_key_on_a_read_is_ignored(): void
    {
        // GET ничего не меняет: запоминать его ответ незачем, а отдать
        // вчерашний статус вместо сегодняшнего — вредно.
        [$project, $key] = $this->projectWithKey();

        $otpLog = OtpLog::factory()->for($project)->pending()->create();

        $this->withHeaders(['X-Api-Key' => $key, 'Idempotency-Key' => 'read-1'])
            ->getJson('/api/v1/otp/'.$otpLog->id)
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertHeaderMissing('Idempotent-Replay');

        $otpLog->markStatus('failed');

        $this->withHeaders(['X-Api-Key' => $key, 'Idempotency-Key' => 'read-1'])
            ->getJson('/api/v1/otp/'.$otpLog->id)
            ->assertOk()
            ->assertJsonPath('status', 'failed');
    }

    /**
     * @return array{0: Project, 1: string}
     */
    private function projectWithKey(): array
    {
        Queue::fake();

        $project = Project::factory()->create();
        $key = ApiKey::generateFor($project);

        return [$project, $key->plainTextKey()];
    }
}
