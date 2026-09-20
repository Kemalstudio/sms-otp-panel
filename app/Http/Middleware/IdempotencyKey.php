<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\IdempotencyKey as IdempotencyRecord;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Повтор запроса не должен стоить второй SMS.
 *
 * Клиент присылает `Idempotency-Key`, мы запоминаем ответ и на повтор с тем же
 * ключом отдаём его же, ничего не выполняя. Это защита не от кривых клиентов, а
 * от обычной сети: таймаут на стороне вызывающего ничего не говорит о том,
 * выполнился запрос или нет, и единственный безопасный ответ на него — ретрай.
 */
class IdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header(self::HEADER, ''));

        // Заголовка нет — обычный запрос. Идемпотентность добровольная.
        if ($key === '') {
            return $next($request);
        }

        // GET и так ничего не меняет: запоминать его ответ незачем, а вот
        // отдать вчерашний статус кода вместо сегодняшнего — вредно.
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if (mb_strlen($key) > 255) {
            return response()->json([
                'message' => 'idempotency key must be at most 255 characters',
            ], Response::HTTP_BAD_REQUEST);
        }

        $apiKey = $request->attributes->get(ApiKeyAuth::REQUEST_KEY);

        // Без ключа проекта хранить нечего: этот middleware всегда идёт после
        // api.key, но полагаться на порядок молча не стоит.
        if (! $apiKey instanceof ApiKey) {
            return $next($request);
        }

        $fingerprint = IdempotencyRecord::fingerprintFor(
            $request->method(),
            $request->path(),
            $request->getContent(),
        );

        $existing = IdempotencyRecord::query()
            ->where('api_key_id', $apiKey->id)
            ->where('key', $key)
            ->first();

        if ($existing) {
            return $this->replay($existing, $fingerprint);
        }

        try {
            $record = IdempotencyRecord::create([
                'api_key_id' => $apiKey->id,
                'key' => $key,
                'fingerprint' => $fingerprint,
                'expires_at' => now()->addHours(IdempotencyRecord::TTL_HOURS),
            ]);
        } catch (QueryException $e) {
            // Гонка: дубль успел вставить строку между нашим чтением и записью.
            // Уникальный индекс — единственное, что здесь надёжно.
            return $this->inFlight();
        }

        $response = $next($request);

        if ($this->shouldRemember($response)) {
            $record->forceFill([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ])->save();
        } else {
            // Отказ «попробуйте позже» — не результат. Строка удаляется, чтобы
            // честный повтор с тем же ключом прошёл, а не упёрся в наш же 429.
            $record->delete();
        }

        return $response;
    }

    private function replay(IdempotencyRecord $record, string $fingerprint): Response
    {
        if (! hash_equals($record->fingerprint, $fingerprint)) {
            return response()->json([
                'message' => 'idempotency key was already used with a different payload',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($record->isInFlight()) {
            return response()->json([
                'message' => 'a request with this idempotency key is still in flight',
            ], Response::HTTP_CONFLICT)->header('Retry-After', '5');
        }

        return response($record->response_body, $record->response_status)
            ->header('Content-Type', 'application/json')
            // Чтобы в логах вызывающего было видно, что это не новая отправка.
            ->header('Idempotent-Replay', 'true');
    }

    private function inFlight(): Response
    {
        return response()->json([
            'message' => 'a request with this idempotency key is still in flight',
        ], Response::HTTP_CONFLICT)->header('Retry-After', '5');
    }

    /**
     * Запоминаем то, что уже случилось или не случится никогда.
     *
     * 5xx и 429 — состояния «сейчас нельзя, попробуйте позже»: запомнить их
     * значило бы залипнуть на сутки, отдавая клиенту его же отказ.
     */
    private function shouldRemember(Response $response): bool
    {
        $status = $response->getStatusCode();

        return $status < 500 && $status !== Response::HTTP_TOO_MANY_REQUESTS;
    }
}
