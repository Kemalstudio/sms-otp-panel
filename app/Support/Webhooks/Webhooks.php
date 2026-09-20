<?php

namespace App\Support\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\OtpLog;
use App\Models\Project;
use App\Models\WebhookDelivery;

/**
 * Точка, откуда уходят события клиенту.
 *
 * Событие всегда сначала становится строкой в webhook_deliveries и только
 * потом — задачей в очереди: если процесс умрёт между этим, останется след, а
 * не тишина.
 */
class Webhooks
{
    /** Что умеет прилетать интегратору. */
    public const EVENTS = [
        'otp.sent',
        'otp.failed',
        'otp.verified',
        'otp.expired',
        'webhook.test',
    ];

    /**
     * Статус лога → событие. Не все переходы интересны: `pending` — это момент
     * постановки в очередь, о нём вызывающий уже знает из ответа на /otp/send.
     */
    public const STATUS_EVENTS = [
        'sent' => 'otp.sent',
        'failed' => 'otp.failed',
        'delivered' => 'otp.verified',
        'expired' => 'otp.expired',
    ];

    public static function eventForStatus(string $status): ?string
    {
        return self::STATUS_EVENTS[$status] ?? null;
    }

    /**
     * Ставит событие в очередь. Молча ничего не делает, если вебхуки у проекта
     * выключены — это не ошибка, а обычная конфигурация.
     */
    public static function send(Project $project, string $event, array $data, ?OtpLog $otpLog = null): ?WebhookDelivery
    {
        if (! $project->hasWebhook()) {
            return null;
        }

        $payload = json_encode([
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /** @var WebhookDelivery $delivery */
        $delivery = $project->webhookDeliveries()->create([
            'otp_log_id' => $otpLog?->id,
            'event' => $event,
            'url' => $project->webhook_url,
            'status' => 'pending',
            'payload' => $payload,
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * Тело события об одном коде. Сам код сюда не попадает никогда — ни в
     * открытом виде, ни в хешированном.
     */
    public static function otpPayload(OtpLog $otpLog): array
    {
        $otpLog->loadMissing('device');

        return [
            'otp_id' => $otpLog->id,
            'phone' => $otpLog->phone,
            'status' => $otpLog->status,
            'attempts' => $otpLog->attempts,
            'device_id' => $otpLog->device_id,
            'from' => $otpLog->device?->phone_number,
            'created_at' => $otpLog->created_at?->toIso8601String(),
            'expires_at' => $otpLog->expires_at?->toIso8601String(),
        ];
    }

    public static function sendOtpEvent(OtpLog $otpLog): ?WebhookDelivery
    {
        $event = self::eventForStatus($otpLog->status);

        if ($event === null) {
            return null;
        }

        $otpLog->loadMissing('project');

        if ($otpLog->project === null) {
            return null;
        }

        return self::send($otpLog->project, $event, self::otpPayload($otpLog), $otpLog);
    }
}
