<?php

namespace App\Support\Webhooks;

/**
 * Подпись исходящих вебхуков.
 *
 * Схема как у Stripe: подписывается не только тело, но и метка времени, а в
 * заголовок уходит `t=<unix>,v1=<hex>`. Без метки времени подписанный запрос
 * можно было бы записать и воспроизвести через месяц; с ней получатель
 * отбрасывает всё старше своего окна.
 */
class WebhookSignature
{
    public const HEADER = 'X-Gateway-Signature';

    /** Сколько получателю разумно принимать запрос: защита от replay. */
    public const TOLERANCE_SECONDS = 300;

    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.self::sign($payload, $secret, $timestamp);
    }

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * Проверка на стороне получателя. Живёт здесь, чтобы тесты проверяли ровно
     * тот алгоритм, который описан в документации для интегратора.
     */
    public static function verify(
        string $payload,
        string $header,
        string $secret,
        int $toleranceSeconds = self::TOLERANCE_SECONDS,
    ): bool {
        $parts = [];

        foreach (explode(',', $header) as $chunk) {
            [$name, $value] = array_pad(explode('=', trim($chunk), 2), 2, null);
            $parts[$name] = $value;
        }

        $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
        $signature = $parts['v1'] ?? '';

        if ($timestamp <= 0 || $signature === '') {
            return false;
        }

        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        // hash_equals, а не ===: сравнение подписей не должно зависеть от того,
        // на каком символе они разошлись.
        return hash_equals(self::sign($payload, $secret, $timestamp), $signature);
    }
}
