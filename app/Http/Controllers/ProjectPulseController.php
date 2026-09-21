<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Живые счётчики для панели.
 *
 * Страницу шлюза держат открытой весь день, и статичная разметка врёт: телефон
 * мог отвалиться десять минут назад. Этот эндпоинт опрашивается раз в
 * несколько секунд и обновляет только цифры и статусы, без перезагрузки.
 */
class ProjectPulseController extends Controller
{
    public function __invoke(Project $project): JsonResponse
    {
        $devices = $project->devices()->get();
        $stats = $project->otpLogs()
            ->whereDate('created_at', now()->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sentToday = (int) $stats->sum();
        $failedToday = (int) ($stats['failed'] ?? 0);

        return response()->json([
            'at' => now()->toIso8601String(),
            'online' => $devices->filter->isOnline()->count(),
            'devices_total' => $devices->count(),
            'throughput' => $project->throughputPerMinute(),
            'sent_today' => $sentToday,
            'failed_today' => $failedToday,
            'success_rate' => $sentToday > 0
                ? (int) round(($sentToday - $failedToday) * 100 / $sentToday)
                : 100,
            'alerts' => $project->alerts()->active()->count(),
            'devices' => $devices->map(fn (Device $device) => [
                'id' => $device->id,
                'status' => $device->effective_status,
                'battery' => $device->battery_level,
                'last_seen' => $device->last_seen_at?->diffForHumans(),
            ])->values(),
        ]);
    }
}
