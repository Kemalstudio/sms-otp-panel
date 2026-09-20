<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Доставляет одно событие на webhook_url проекта.
 *
 * Ретраи обязательны: чужой сервер уходит на деплой, отвечает 502 и возвращается
 * через минуту. Без повторов интегратор просто теряет статус кода — а именно
 * ради статуса вебхуки и делаются.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    /** Пять попыток примерно за 20 минут — этого хватает на обычный деплой. */
    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public int $deliveryId)
    {
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('project')->find($this->deliveryId);

        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $project = $delivery->project;

        if (! $project || ! $project->hasWebhook()) {
            $delivery->markFailed(null, 'вебхуки у проекта выключены', final: true);

            return;
        }

        $delivery->forceFill(['attempts' => $delivery->attempts + 1])->save();

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'sms-otp-gateway/1.0',
                WebhookSignature::HEADER => WebhookSignature::header(
                    $delivery->payload,
                    $project->webhook_secret,
                ),
                'X-Gateway-Event' => $delivery->event,
                'X-Gateway-Delivery' => (string) $delivery->id,
            ])
                // Чужой сервер не должен держать нашего воркера: 10 секунд и
                // повтор по расписанию лучше, чем зависшая очередь.
                ->timeout(10)
                ->connectTimeout(5)
                ->withBody($delivery->payload, 'application/json')
                ->post($delivery->url);
        } catch (Throwable $e) {
            $this->fail($delivery, null, $e->getMessage());

            return;
        }

        if ($response->successful()) {
            $delivery->markDelivered($response->status());

            return;
        }

        $this->fail($delivery, $response->status(), 'ответ '.$response->status());
    }

    /**
     * Последняя попытка помечает доставку окончательно проваленной — до этого
     * она остаётся pending, чтобы в панели не мелькало «failed» между ретраями.
     */
    private function fail(WebhookDelivery $delivery, ?int $status, string $error): void
    {
        $final = $delivery->attempts >= $this->tries;

        $delivery->markFailed($status, $error, $final);

        Log::warning('webhook delivery failed', [
            'delivery_id' => $delivery->id,
            'attempt' => $delivery->attempts,
            'error' => $error,
        ]);

        if (! $final) {
            // Бросаем, чтобы очередь применила backoff и вернула задачу.
            throw new \RuntimeException('webhook delivery failed: '.$error);
        }
    }
}
