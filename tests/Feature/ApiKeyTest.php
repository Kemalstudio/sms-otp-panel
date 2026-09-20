<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_key_stores_only_its_hash_and_shows_it_once(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($project->user)
            ->post(route('projects.api-keys.store', $project))
            ->assertRedirect(route('projects.api-keys.index', $project))
            ->assertSessionHas('new_api_key');

        $plain = $response->getSession()->get('new_api_key');
        $key = $project->apiKeys()->sole();

        $this->assertStringStartsWith(ApiKey::PREFIX, $plain);
        $this->assertSame(hash('sha256', $plain), $key->key_hash);
        $this->assertSame(substr($plain, 0, strlen(ApiKey::PREFIX) + 8), $key->key_prefix);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $plain]);

        // Shown exactly once: the redirected-to page renders the plaintext,
        // and the visit after that no longer has it.
        $this->actingAs($project->user)
            ->get(route('projects.api-keys.index', $project))
            ->assertOk()
            ->assertSee($plain);

        $this->actingAs($project->user)
            ->get(route('projects.api-keys.index', $project))
            ->assertOk()
            ->assertDontSee($plain);
    }

    public function test_revoking_a_key_marks_it_revoked(): void
    {
        $project = Project::factory()->create();
        $key = ApiKey::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->delete(route('projects.api-keys.destroy', [$project, $key]))
            ->assertRedirect(route('projects.api-keys.index', $project));

        $this->assertNotNull($key->fresh()->revoked_at);
    }
}
