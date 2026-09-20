<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexOtpLogRequest;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Contracts\View\View;

class OtpLogController extends Controller
{
    public function index(IndexOtpLogRequest $request, Project $project): View
    {
        $logs = $project->otpLogs()
            ->with('device')
            ->status($request->status())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('projects.logs.index', [
            'project' => $project,
            'logs' => $logs,
            'statuses' => OtpLog::STATUSES,
            'activeStatus' => $request->status(),
        ]);
    }
}
