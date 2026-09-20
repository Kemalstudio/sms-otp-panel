<?php

namespace App\Http\Controllers;

use App\Models\PairingCode;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class PairingCodeController extends Controller
{
    /**
     * Issues a fresh 6-character pairing code for the project and reopens the
     * devices page with the QR modal showing. The code is good for
     * PairingCode::LIFETIME_MINUTES and for a single handset.
     *
     * Ownership is enforced by `can:update,project` on the route.
     */
    public function store(Project $project): RedirectResponse
    {
        PairingCode::issueFor($project);

        return redirect()
            ->route('projects.devices.index', $project)
            ->with('show_pairing', true);
    }
}
