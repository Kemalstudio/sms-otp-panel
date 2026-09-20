<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\IdempotencyKey;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Повтор запроса после сетевого таймаута не должен стоить второй SMS.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_a_repeat_with_the_same_key_replays_the_first_answer(): void
    {
        [$key] = $this->gateway();

        $first = $this->send($key, '+99361000001', 'order-42')->assertAccepted();

        $second = $this->send($key, '+99361000001', 'order-42')
            ->assertAccepted()
            ->assertHeader('Idempotent-Replay', 'true');

        $this->assertSame($first->json('otp_id'), $second->json('otp_id'));

        // Главное: второй SMS не существует.
        $this->assertSame(1, OtpLog::count());
    }

    public function test_the_replay_survives_the_per_phone_rate_limit(): void
    {
        // Без идемпотентности повтор упёрся бы в «один код на номер в минуту»
        // и вызывающий получил бы 429 на запрос, который на самом деле удался.
        [$key] = $this->gateway();

        $this->send($key, '+99361000001', 'order-42')->assertAccepted();

        $this->send($key, '+99361000001', 'order-42')
            ->assertAccepted()
            ->assertHeader('Idempotent-Replay', 'true');
    }

    public function test_the_same_key_with_another_payload_is_a_caller_error(): void
    {
        [$key] = $this->gateway();

        $this->send($key, '+99361000001', 'order-42')->assertAccepted();

        $this->send($key, '+99361000002', 'order-42')
            ->assertStatus(422)
            ->assertJsonPath('message', 'idempotency key was already used with a different payload');

        $this->assertSame(1, OtpLog::count());
    }

    public function test_different_keys_are_different_requests(): void
    {
        [$key] = $this->gateway();

        $this->send($key, '+99361000001', 'order-42')->assertAccepted();
        $this->send($key, '+99361000002', 'order-43')->assertAccepted();

        $this->assertSame(2, OtpLog::count());
    }

    public function test_a_key_of_one_customer_does_not_reach_another(): void
    {
        [$first] = $this->gateway();
        [$second] = $this->gateway();

        $this->send($first, '+99361000001', 'order-42')->assertAccepted();

        // Тот же "order-42", но чужой API-ключ: это другой запрос, а не повтор.
        $this->send($second, '+99361000001', 'order-42')
            ->assertAccepted()
            ->assertHeaderMissing('Idempotent-Replay');

        $this->assertSame(2, OtpLog::count());
    }

    public function test_a_request_without_the_header_works_as_before(): void
    {
        [$key] = $this->gateway();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => '+99361000001'])
            ->assertAccepted();

        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_a_try_later_refusal_is_not_remembered(): void
    {
        // Пул забит: ответ 429 означает «сейчас нельзя», а не результат.
        // Запомнить его значило бы на сутки залипнуть на собственном отказе.
        [$key] = $this->gateway(throughput: 1);

        $this->send($key, '+99361000001', 'first')->assertAccepted();
        $this->send($key, '+99361000002', 'order-42')->assertStatus(429);

        $this->assertSame(0, IdempotencyKey::where('key', 'order-42')->count());

        $this->travel(61)->seconds();

        $this->send($key, '+99361000002', 'order-42')->assertAccepted();
    }

    public function test_a_parallel_duplicate_is_told_to_wait(): void
    {
        [$key, $apiKey] = $this->gateway();

        // Строка без ответа — ровно то, что видит второй запрос, пока первый
        // ещё выполняется.
        IdempotencyKey::create([
            'api_key_id' => $apiKey->id,
            'key' => 'order-42',
            'fingerprint' => IdempotencyKey::fingerprintFor(
                'POST',
                'api/v1/otp/send',
                json_encode(['phone' => '+99361000001']),
            ),
            'expires_at' => now()->addHours(IdempotencyKey::TTL_HOURS),
        ]);

        $this->send($key, '+99361000001', 'order-42')
            ->assertStatus(409)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'a request with this idempotency key is still in flight');

        $this->assertSame(0, OtpLog::count());
    }

    public function test_an_oversized_key_is_rejected(): void
    {
        [$key] = $this->gateway();

        $this->send($key, '+99361000001', str_repeat('x', 256))
            ->assertStatus(400)
            ->assertJsonPath('message', 'idempotency key must be at most 255 characters');
    }

    public function test_expired_records_are_swept(): void
    {
        [$key] = $this->gateway();

        $this->send($key, '+99361000001', 'order-42')->assertAccepted();

        $this->travel(IdempotencyKey::TTL_HOURS + 1)->hours();

        $this->artisan('idempotency:sweep-expired')->assertSuccessful();

        $this->assertSame(0, IdempotencyKey::count());
    }

    /**
     * @return array{0: string, 1: ApiKey}
     */
    private function gateway(int $throughput = 30): array
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create([
            'throughput_per_minute' => $throughput,
        ]);

        $key = ApiKey::generateFor($project);

        return [$key->plainTextKey(), $key];
    }

    private function send(string $key, string $phone, string $idempotencyKey)
    {
        return $this->withHeaders([
            'X-Api-Key' => $key,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/otp/send', ['phone' => $phone]);
    }
}
