<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpVerifyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_code_verifies_the_otp(): void
    {
        [$project, $key] = $this->projectWithKey();
        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '123456'])
            ->assertOk()
            ->assertExactJson(['verified' => true]);

        $otpLog->refresh();

        $this->assertSame('delivered', $otpLog->status);
        $this->assertSame(0, $otpLog->attempts);
        $this->assertNull($otpLog->code_encrypted);
    }

    public function test_wrong_code_counts_an_attempt_and_reports_the_remainder(): void
    {
        [$project, $key] = $this->projectWithKey();
        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '000000'])
            ->assertOk()
            ->assertExactJson(['verified' => false, 'attempts_left' => 4]);

        $otpLog->refresh();

        $this->assertSame(1, $otpLog->attempts);
        $this->assertSame('pending', $otpLog->status);
    }

    public function test_the_otp_burns_after_five_wrong_attempts(): void
    {
        [$project, $key] = $this->projectWithKey();
        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create();

        for ($attempt = 1; $attempt <= OtpLog::MAX_VERIFY_ATTEMPTS; $attempt++) {
            $this->withHeader('X-Api-Key', $key)
                ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '000000'])
                ->assertOk()
                ->assertJsonPath('attempts_left', OtpLog::MAX_VERIFY_ATTEMPTS - $attempt);
        }

        $this->assertSame('failed', $otpLog->fresh()->status);

        // Even the right code no longer works.
        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '123456'])
            ->assertStatus(429)
            ->assertJsonPath('verified', false)
            ->assertJsonPath('attempts_left', 0);

        $this->assertSame('failed', $otpLog->fresh()->status);
    }

    public function test_expired_otp_returns_410(): void
    {
        [$project, $key] = $this->projectWithKey();
        $otpLog = OtpLog::factory()->for($project)->expired()->withCode('123456')->create();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '123456'])
            ->assertStatus(410)
            ->assertJsonPath('message', 'expired');

        $this->assertSame('expired', $otpLog->fresh()->status);
    }

    public function test_a_verified_code_cannot_be_reused(): void
    {
        [$project, $key] = $this->projectWithKey();
        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '123456'])
            ->assertOk();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '123456'])
            ->assertStatus(410)
            ->assertJsonPath('message', 'already verified');
    }

    public function test_verify_needs_a_valid_api_key(): void
    {
        [$project] = $this->projectWithKey();
        $otpLog = OtpLog::factory()->for($project)->pending()->withCode('123456')->create();

        $this->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '123456'])
            ->assertUnauthorized();

        $this->withHeader('X-Api-Key', 'sk_live_nonsense')
            ->postJson('/api/v1/otp/verify', ['otp_id' => $otpLog->id, 'code' => '123456'])
            ->assertUnauthorized();

        $this->assertSame('pending', $otpLog->fresh()->status);
    }

    public function test_an_otp_of_another_project_is_not_found(): void
    {
        [, $key] = $this->projectWithKey();
        $foreign = OtpLog::factory()->pending()->withCode('123456')->create();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['otp_id' => $foreign->id, 'code' => '123456'])
            ->assertNotFound()
            ->assertJsonPath('message', 'otp not found');

        $this->assertSame('pending', $foreign->fresh()->status);
    }

    public function test_verify_validates_its_payload(): void
    {
        [, $key] = $this->projectWithKey();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/otp/verify', ['code' => '12'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['otp_id', 'code']);
    }

    /**
     * @return array{0: Project, 1: string}
     */
    private function projectWithKey(): array
    {
        $project = Project::factory()->create();

        return [$project, ApiKey::generateFor($project)->plainTextKey()];
    }
}
