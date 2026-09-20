<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\OtpController;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OtpRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_the_same_phone_may_only_be_messaged_once_a_minute(): void
    {
        [$key] = $this->projectWithOnlineDevice();

        $this->send($key, '+99365123456')->assertStatus(202);

        $second = $this->send($key, '+99365123456');

        $second->assertStatus(429)
            ->assertJsonPath('message', 'too many requests for this phone number')
            ->assertHeader('Retry-After');

        $this->assertLessThanOrEqual(
            OtpController::PER_PHONE_DECAY_SECONDS,
            $second->json('retry_after')
        );

        // Nothing was written for the rejected request.
        $this->assertSame(1, OtpLog::count());

        // A different number is unaffected.
        $this->send($key, '+99365123457')->assertStatus(202);

        // ...and so is the same number once the window has passed.
        $this->travel(OtpController::PER_PHONE_DECAY_SECONDS + 1)->seconds();
        $this->send($key, '+99365123456')->assertStatus(202);
    }

    public function test_the_same_number_on_another_project_is_not_blocked(): void
    {
        [$firstKey] = $this->projectWithOnlineDevice();
        [$secondKey] = $this->projectWithOnlineDevice();

        $this->send($firstKey, '+99365123456')->assertStatus(202);
        $this->send($secondKey, '+99365123456')->assertStatus(202);
    }

    public function test_an_api_key_is_capped_at_twenty_requests_a_minute(): void
    {
        [$key] = $this->projectWithOnlineDevice();

        // Distinct numbers, so only the per-key ceiling can trip.
        for ($i = 0; $i < OtpController::PER_KEY_LIMIT; $i++) {
            $this->send($key, '+9936512'.str_pad((string) $i, 4, '0', STR_PAD_LEFT))
                ->assertStatus(202);
        }

        $this->send($key, '+99365129999')
            ->assertStatus(429)
            ->assertJsonPath('message', 'too many requests for this api key');

        $this->assertSame(OtpController::PER_KEY_LIMIT, OtpLog::count());

        $this->travel(OtpController::PER_KEY_DECAY_SECONDS + 1)->seconds();

        $this->send($key, '+99365129999')->assertStatus(202);
    }

    public function test_the_per_key_ceiling_does_not_consume_the_phone_budget(): void
    {
        [$key] = $this->projectWithOnlineDevice();

        for ($i = 0; $i < OtpController::PER_KEY_LIMIT; $i++) {
            $this->send($key, '+9936512'.str_pad((string) $i, 4, '0', STR_PAD_LEFT))
                ->assertStatus(202);
        }

        // Rejected by the key limit; this number must not be marked as used.
        $this->send($key, '+99365129999')->assertStatus(429);

        $this->travel(OtpController::PER_KEY_DECAY_SECONDS + 1)->seconds();

        $this->send($key, '+99365129999')->assertStatus(202);
    }

    public function test_two_keys_of_one_project_have_separate_budgets(): void
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create([
            'throughput_per_minute' => Device::MAX_THROUGHPUT_PER_MINUTE,
        ]);

        $first = ApiKey::generateFor($project)->plainTextKey();
        $second = ApiKey::generateFor($project)->plainTextKey();

        for ($i = 0; $i < OtpController::PER_KEY_LIMIT; $i++) {
            $this->send($first, '+9936512'.str_pad((string) $i, 4, '0', STR_PAD_LEFT))
                ->assertStatus(202);
        }

        $this->send($first, '+99365129999')->assertStatus(429);
        $this->send($second, '+99365129999')->assertStatus(202);
    }

    private function send(string $key, string $phone): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/send', ['phone' => $phone]);
    }

    /**
     * @return array{0: string, 1: Project}
     */
    /**
     * Телефон с максимальной скоростью: здесь проверяются лимиты вызывающего,
     * и пропускная способность шлюза не должна их подменять — при дефолтных
     * 10 SMS/мин потолок ключа в 20 запросов просто не был бы достигнут.
     */
    private function projectWithOnlineDevice(): array
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->online()->create([
            'throughput_per_minute' => Device::MAX_THROUGHPUT_PER_MINUTE,
        ]);

        return [ApiKey::generateFor($project)->plainTextKey(), $project];
    }
}
