<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\DeviceTokenAuth;
use App\Http\Requests\Api\ReportOtpStatusRequest;
use App\Models\Device;
use App\Models\OtpLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints a paired phone calls about itself. Authenticated by
 * X-Device-Token, never by a project API key.
 */
class DeviceStatusController extends Controller
{
    /**
     * Keeps the device inside the online window so it stays dispatchable.
     *
     * The phone re-states its push address on every beat: FCM rotates a token
     * on its own schedule, and without this the gateway would keep pushing into
     * a dead address until someone noticed that one handset stopped delivering.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->device($request);

        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string', 'max:255'],
            // Стойку из телефонов надо видеть целиком: разряженный аппарат
            // выпадет из пула через несколько часов, и лучше узнать заранее.
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $token = $validated['fcm_token'] ?? null;

        if ($token !== null && $token !== '' && $token !== $device->fcm_token) {
            $device->forceFill(['fcm_token' => $token])->save();
        }

        if (array_key_exists('battery_level', $validated) && $validated['battery_level'] !== null) {
            $device->forceFill(['battery_level' => $validated['battery_level']])->save();
        }

        $device->markSeen();

        return response()->json([
            'device_id' => $device->id,
            'status' => $device->status,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            // Телефон узнаёт свой лимит из панели, а не хранит его у себя.
            'throughput_per_minute' => $device->throughput_per_minute,
        ]);
    }

    /**
     * The phone reporting what happened to an SMS it was asked to send.
     */
    public function reportStatus(ReportOtpStatusRequest $request): JsonResponse
    {
        $device = $this->device($request);

        // Only logs actually dispatched to this device may be reported on.
        $otpLog = OtpLog::query()
            ->where('device_id', $device->id)
            ->find($request->validated('otp_id'));

        if (! $otpLog) {
            return response()->json(['message' => 'otp not found'], Response::HTTP_NOT_FOUND);
        }

        // A late report must not undo a successful verify or a resolved failure.
        if ($otpLog->status === 'pending') {
            $otpLog->markStatus($request->validated('status'));
        }

        // Reporting in is itself a sign of life.
        $device->markSeen();

        return response()->json([
            'otp_id' => $otpLog->id,
            'status' => $otpLog->status,
        ]);
    }

    private function device(Request $request): Device
    {
        return $request->attributes->get(DeviceTokenAuth::REQUEST_DEVICE);
    }
}
