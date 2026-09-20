<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Project cards for the authenticated user, with the two counters the
     * dashboard shows: online devices and OTPs created today.
     */
    public function index(Request $request): View
    {
        $projects = $request->user()->projects()
            ->withCount([
                'devices as online_devices_count' => fn (Builder $query) => $query->online(),
                'otpLogs as otp_today_count' => fn (Builder $query) => $query->whereDate('created_at', now()->toDateString()),
            ])
            ->latest()
            ->get();

        return view('dashboard', [
            'projects' => $projects,
            'onlineThresholdMinutes' => Device::ONLINE_THRESHOLD_MINUTES,
        ]);
    }
}
