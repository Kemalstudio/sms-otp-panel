<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a customer integration by its project API key.
 *
 * The plaintext key is only ever compared as a sha256 hash, and the resolved
 * key + project are attached to the request for the controllers.
 */
class ApiKeyAuth
{
    public const REQUEST_KEY = 'api_key';

    public const REQUEST_PROJECT = 'api_project';

    public function handle(Request $request, Closure $next): Response
    {
        $plain = trim((string) $request->header('X-Api-Key'));

        if ($plain === '') {
            return $this->unauthorized('missing api key');
        }

        $apiKey = ApiKey::query()
            ->active()
            ->where('key_hash', ApiKey::hashKey($plain))
            ->first();

        if (! $apiKey) {
            return $this->unauthorized('invalid api key');
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set(self::REQUEST_KEY, $apiKey);
        $request->attributes->set(self::REQUEST_PROJECT, $apiKey->project);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED);
    }
}
