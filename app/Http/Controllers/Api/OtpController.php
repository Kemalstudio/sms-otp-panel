<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Requests\Api\SendOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Jobs\SendOtpViaFcmJob;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer-facing OTP API. Authenticated by X-Api-Key (ApiKeyAuth),
 * which resolves the owning project onto the request.
 */
class OtpController extends Controller
{
    /** One OTP per phone number per minute. */
    public const PER_PHONE_LIMIT = 1;

    public const PER_PHONE_DECAY_SECONDS = 60;

    /** Burst ceiling for a single API key. */
    public const PER_KEY_LIMIT = 20;

    public const PER_KEY_DECAY_SECONDS = 60;

    public function send(SendOtpRequest $request): JsonResponse
    {
        $project = $this->project($request);
        $apiKey = $this->apiKey($request);
        $phone = $request->validated('phone');

        // Checked before anything is consumed or written.
        if ($limited = $this->rateLimitExceeded($apiKey, $project, $phone)) {
            return $limited;
        }

        // Отправитель можно зафиксировать: у проекта может быть несколько SIM,
        // и бизнесу иногда важно, с какого номера придёт код.
        $from = $request->validated('from');

        if ($from !== null && ! $project->devices()->where('phone_number', $from)->exists()) {
            return response()->json([
                'message' => 'unknown sender number',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /*
         * Пул резолвится до списания бюджета: и пустой пул, и упёршаяся
         * пропускная способность — проблемы шлюза, а не вызывающего, и не
         * должны съедать его слот «один код на номер в минуту».
         */
        $pool = $project->dispatchableDevices([], $from);
        $device = $pool->first(fn (Device $candidate) => $candidate->hasCapacity());

        if (! $device && $pool->isEmpty()) {
            $otpLog = $project->otpLogs()->create([
                'code_hash' => Hash::make(self::generateCode()),
                'phone' => $phone,
                'status' => 'failed',
                'expires_at' => now()->addMinutes(OtpLog::LIFETIME_MINUTES),
            ]);

            return response()->json([
                'otp_id' => $otpLog->id,
                'status' => 'failed',
                'message' => 'no active device available',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $device) {
            /*
             * Все телефоны на связи, но каждый выбрал свой лимит за минуту.
             * Здесь сознательно отказ, а не очередь: код живёт 5 минут, и
             * SMS, доставленная после его истечения, хуже честного отказа —
             * клиент успеет нажать «отправить ещё раз».
             */
            $retryAfter = (int) $pool->map->secondsUntilCapacity()->min();

            return response()->json([
                'status' => 'rejected',
                'message' => 'all devices are at their throughput limit',
                'retry_after' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS)->header('Retry-After', $retryAfter);
        }

        $this->consumeRateLimit($apiKey, $project, $phone);

        $code = self::generateCode();

        /** @var OtpLog $otpLog */
        $otpLog = $project->otpLogs()->create([
            // bcrypt, used by verify. Never reversed.
            'code_hash' => Hash::make($code),
            // Reversible copy, used once to build the SMS body, then nulled.
            'code_encrypted' => $code,
            'device_id' => $device->id,
            'phone' => $phone,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(OtpLog::LIFETIME_MINUTES),
        ]);

        // Moves this device to the back of the round-robin queue.
        $device->markDispatched();

        SendOtpViaFcmJob::dispatch($otpLog->id, $device->id);

        return response()->json([
            'otp_id' => $otpLog->id,
            'status' => $otpLog->status,
            // С какого номера уйдёт SMS: у проекта может быть несколько SIM.
            'from' => $device->phone_number,
            'expires_at' => $otpLog->expires_at->toIso8601String(),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Статус одного кода.
     *
     * Запасной путь к вебхукам: если приёмник лежал дольше, чем живут ретраи,
     * узнать судьбу кода больше неоткуда. Сам код здесь не отдаётся ни в каком
     * виде — только то, что вызывающий про него и так знает.
     */
    public function show(Request $request, int $otpId): JsonResponse
    {
        $project = $this->project($request);

        // Через связь проекта: чужой код отвечает 404, а не «нет доступа» —
        // существование чужих идентификаторов тоже не наше дело раскрывать.
        $otpLog = $project->otpLogs()->with('device')->find($otpId);

        if (! $otpLog) {
            return response()->json(['message' => 'otp not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'otp_id' => $otpLog->id,
            'phone' => $otpLog->phone,
            'status' => $otpLog->status,
            'attempts' => $otpLog->attempts,
            'attempts_left' => $otpLog->attemptsLeft(),
            'device_id' => $otpLog->device_id,
            'from' => $otpLog->device?->phone_number,
            'created_at' => $otpLog->created_at?->toIso8601String(),
            'expires_at' => $otpLog->expires_at?->toIso8601String(),
        ]);
    }

    private static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $project = $this->project($request);

        // Scoped to the key's own project: one customer cannot verify another's.
        $otpLog = $project->otpLogs()->find($request->validated('otp_id'));

        if (! $otpLog) {
            return response()->json(['message' => 'otp not found'], Response::HTTP_NOT_FOUND);
        }

        if ($otpLog->isExpired()) {
            if (in_array($otpLog->status, ['pending', 'sent'], true)) {
                $otpLog->markStatus('expired');
            }

            return response()->json(['message' => 'expired'], Response::HTTP_GONE);
        }

        // A code is good for exactly one successful verification.
        if ($otpLog->status === 'delivered') {
            return response()->json(['message' => 'already verified'], Response::HTTP_GONE);
        }

        if (! $otpLog->hasAttemptsLeft()) {
            return response()->json([
                'verified' => false,
                'attempts_left' => 0,
                'message' => 'too many attempts',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (Hash::check($request->validated('code'), $otpLog->code_hash)) {
            // "delivered" doubles as the terminal success state for a verified code.
            $otpLog->forceFill(['status' => 'delivered', 'code_encrypted' => null])->save();

            return response()->json(['verified' => true]);
        }

        $otpLog->forceFill(['attempts' => $otpLog->attempts + 1])->save();

        if (! $otpLog->hasAttemptsLeft()) {
            // Burned: no further attempts are accepted, even before expiry.
            $otpLog->markStatus('failed');
        }

        return response()->json([
            'verified' => false,
            'attempts_left' => $otpLog->attemptsLeft(),
        ]);
    }

    /**
     * Both limits are inspected without being charged, so a request rejected
     * by one does not eat the other's budget.
     */
    private function rateLimitExceeded(ApiKey $apiKey, Project $project, string $phone): ?JsonResponse
    {
        $apiKeyKey = self::apiKeyLimiterKey($apiKey);
        $phoneKey = self::phoneLimiterKey($project, $phone);

        if (RateLimiter::tooManyAttempts($apiKeyKey, self::PER_KEY_LIMIT)) {
            return $this->tooManyRequests(
                'too many requests for this api key',
                RateLimiter::availableIn($apiKeyKey)
            );
        }

        if (RateLimiter::tooManyAttempts($phoneKey, self::PER_PHONE_LIMIT)) {
            return $this->tooManyRequests(
                'too many requests for this phone number',
                RateLimiter::availableIn($phoneKey)
            );
        }

        return null;
    }

    /**
     * Charged only once the request is actually going to produce an SMS.
     */
    private function consumeRateLimit(ApiKey $apiKey, Project $project, string $phone): void
    {
        RateLimiter::hit(self::apiKeyLimiterKey($apiKey), self::PER_KEY_DECAY_SECONDS);
        RateLimiter::hit(self::phoneLimiterKey($project, $phone), self::PER_PHONE_DECAY_SECONDS);
    }

    private static function apiKeyLimiterKey(ApiKey $apiKey): string
    {
        return "otp:send:key:{$apiKey->id}";
    }

    private static function phoneLimiterKey(Project $project, string $phone): string
    {
        return "otp:send:project:{$project->id}:phone:".sha1($phone);
    }

    private function tooManyRequests(string $message, int $retryAfter): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'retry_after' => $retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS)->header('Retry-After', (string) $retryAfter);
    }

    private function project(Request $request): Project
    {
        return $request->attributes->get(ApiKeyAuth::REQUEST_PROJECT);
    }

    private function apiKey(Request $request): ApiKey
    {
        return $request->attributes->get(ApiKeyAuth::REQUEST_KEY);
    }
}
