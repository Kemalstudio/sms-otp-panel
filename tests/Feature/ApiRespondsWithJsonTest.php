<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gateway is consumed by phones and server integrations, never a browser.
 *
 * These use `post`/`postJson` without an `Accept: application/json` header on
 * purpose — curl and most SDKs omit it, and Laravel's default reaction to a
 * failed validation for such a caller is a 302 back to an HTML page.
 */
class ApiRespondsWithJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_are_json_even_without_an_accept_header(): void
    {
        $project = Project::factory()->create();
        $key = ApiKey::generateFor($project);

        $response = $this->post(
            '/api/v1/otp/send',
            ['phone' => 'not-a-number'],
            ['X-Api-Key' => $key->plainTextKey()]
        );

        $response->assertStatus(422)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonValidationErrors('phone');
    }

    public function test_verify_validation_errors_are_json_too(): void
    {
        $project = Project::factory()->create();
        $key = ApiKey::generateFor($project);

        $this->post('/api/v1/otp/verify', [], ['X-Api-Key' => $key->plainTextKey()])
            ->assertStatus(422)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonValidationErrors(['otp_id', 'code']);
    }

    public function test_pairing_validation_errors_are_json_too(): void
    {
        $this->post('/api/v1/devices/pair', ['pairing_code' => 'X'])
            ->assertStatus(422)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonValidationErrors(['device_name']);
    }

    public function test_an_unknown_api_route_answers_with_json_not_html(): void
    {
        $response = $this->post('/api/v1/does-not-exist');

        $response->assertStatus(404)
            ->assertHeader('content-type', 'application/json');
    }
}
