<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiKeyRequest;
use App\Models\ApiKey;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ApiKeyController extends Controller
{
    public function index(Project $project): View
    {
        return view('projects.api-keys.index', [
            'project' => $project,
            'apiKeys' => $project->apiKeys()->latest()->get(),
        ]);
    }

    /**
     * The plaintext key travels back exactly once, in a one-shot flash message.
     * Only its sha256 hash is stored.
     */
    public function store(StoreApiKeyRequest $request, Project $project): RedirectResponse
    {
        $apiKey = ApiKey::generateFor($project);

        return redirect()
            ->route('projects.api-keys.index', $project)
            ->with('new_api_key', $apiKey->plainTextKey());
    }

    public function destroy(Project $project, ApiKey $apiKey): RedirectResponse
    {
        $apiKey->revoke();

        return redirect()
            ->route('projects.api-keys.index', $project)
            ->with('status', 'Ключ '.$apiKey->key_prefix.'… отозван.');
    }
}
