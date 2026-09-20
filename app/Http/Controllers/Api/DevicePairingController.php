<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PairDeviceRequest;
use App\Models\Device;
use App\Models\PairingCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DevicePairingController extends Controller
{
    /**
     * Claims a pairing code shown in the panel and registers the phone.
     *
     * Public on purpose: the short-lived, single-use code is the credential.
     * The device token returned here is the only one ever issued for this
     * device and is not recoverable afterwards.
     */
    public function store(PairDeviceRequest $request): JsonResponse
    {
        $code = $request->validated('pairing_code');

        $paired = DB::transaction(function () use ($code, $request) {
            // Locked re-read: two phones scanning the same QR must not both win.
            $pairingCode = PairingCode::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $pairingCode || ! $pairingCode->isUsable()) {
                return null;
            }

            /** @var Device $device */
            $device = $pairingCode->project->devices()->create([
                'name' => $request->validated('device_name'),
                'phone_number' => $request->validated('phone_number'),
                'status' => 'active',
                'last_seen_at' => now(),
                'fcm_token' => $request->validated('fcm_token'),
            ]);

            $token = $device->issueToken();
            $pairingCode->markUsed();

            return ['device' => $device, 'token' => $token];
        });

        if ($paired === null) {
            return response()->json(
                ['message' => 'invalid or expired code'],
                Response::HTTP_NOT_FOUND
            );
        }

        return response()->json([
            'device_id' => $paired['device']->id,
            'device_name' => $paired['device']->name,
            'phone_number' => $paired['device']->phone_number,
            'project_id' => $paired['device']->project_id,
            // Shown once. The server only keeps its sha256 hash.
            'device_token' => $paired['token'],
        ], Response::HTTP_CREATED);
    }
}
