<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запомненный ответ на запрос с заголовком Idempotency-Key.
 *
 * Строка создаётся до выполнения запроса и получает тело ответа после —
 * промежуток между этим и есть «запрос в полёте», по которому ловится
 * параллельный дубль.
 */
class IdempotencyKey extends Model
{
    /** Сутки: столько живёт право повторить запрос и получить тот же ответ. */
    public const TTL_HOURS = 24;

    protected $fillable = [
        'api_key_id',
        'key',
        'fingerprint',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'response_status' => 'integer',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** Ответ ещё не записан — значит первый запрос всё ещё выполняется. */
    public function isInFlight(): bool
    {
        return $this->response_status === null;
    }

    public static function fingerprintFor(string $method, string $path, string $body): string
    {
        return hash('sha256', $method.'|'.$path.'|'.$body);
    }
}
