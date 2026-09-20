<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a paired phone by the device token it received once at
 * pairing time. Unlike ApiKeyAuth this identifies a single handset, not a
 * customer integration.
 */
class DeviceTokenAuth
{
    public const REQUEST_DEVICE = 'device';

    public function handle(Request $request, Closure $next): Response
    {
        $plain = trim((string) $request->header('X-Device-Token'));

        if ($plain === '') {
            return response()->json(['message' => 'missing device token'], Response::HTTP_UNAUTHORIZED);
        }

        $device = Device::query()
            ->where('token_hash', Device::hashToken($plain))
            ->first();

        if (! $device) {
            return response()->json(['message' => 'invalid device token'], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set(self::REQUEST_DEVICE, $device);

        return $next($request);
    }
}
