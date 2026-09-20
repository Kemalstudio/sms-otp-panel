<?php

namespace App\Support\Alerts;

use App\Models\Alert;
use App\Models\Project;
use App\Notifications\GatewayAlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Открытие и закрытие аварий.
 *
 * Главное здесь — не «отправить письмо», а «отправить его один раз».
 * Проверка здоровья гоняется каждую минуту; без состояния в базе владелец
 * проекта получал бы по письму в минуту и через сутки завёл бы правило
 * «в спам».
 */
class AlertManager
{
    /**
     * Авария происходит прямо сейчас. Если она уже открыта — только
     * продлевается, без повторного уведомления.
     */
    public function raise(string $type, string $message, ?Project $project = null): Alert
    {
        $existing = $this->activeAlert($type, $project);

        if ($existing) {
            $existing->forceFill([
                'message' => $message,
                'last_seen_at' => now(),
            ])->save();

            return $existing;
        }

        /** @var Alert $alert */
        $alert = Alert::create([
            'project_id' => $project?->id,
            'type' => $type,
            'message' => $message,
            'status' => 'active',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->announce($alert, opened: true);

        return $alert;
    }

    /** Условие больше не выполняется: закрываем и сообщаем о восстановлении. */
    public function clear(string $type, ?Project $project = null): void
    {
        $alert = $this->activeAlert($type, $project);

        if (! $alert) {
            return;
        }

        $alert->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
        ])->save();

        $this->announce($alert, opened: false);
    }

    private function activeAlert(string $type, ?Project $project): ?Alert
    {
        return Alert::query()
            ->active()
            ->where('type', $type)
            ->where('project_id', $project?->id)
            ->first();
    }

    /**
     * Письмо владельцу (или дежурному для общесистемных аварий) плюс Telegram.
     *
     * Всё синхронно и намеренно: уведомление об упавшей очереди не должно
     * ехать через упавшую очередь.
     */
    private function announce(Alert $alert, bool $opened): void
    {
        Log::warning($opened ? 'gateway alert raised' : 'gateway alert resolved', [
            'type' => $alert->type,
            'project_id' => $alert->project_id,
            'message' => $alert->message,
        ]);

        Telegram::send($this->telegramText($alert, $opened));

        $email = $this->recipient($alert);

        if ($email === null) {
            return;
        }

        Notification::route('mail', $email)
            ->notify(new GatewayAlertNotification($alert, $opened));
    }

    private function recipient(Alert $alert): ?string
    {
        // У проектной аварии есть владелец; у общесистемной — только дежурный
        // адрес из конфига, если его задали.
        $alert->loadMissing('project.user');

        return $alert->project?->user?->email
            ?? config('gateway.alerts.ops_email');
    }

    private function telegramText(Alert $alert, bool $opened): string
    {
        $scope = $alert->project ? 'Проект «'.$alert->project->name.'»' : 'Шлюз';

        if (! $opened) {
            return '✅ <b>Восстановлено</b>'.PHP_EOL
                .$scope.PHP_EOL
                .$alert->title;
        }

        return '🚨 <b>'.$alert->title.'</b>'.PHP_EOL
            .$scope.PHP_EOL
            .$alert->message;
    }
}
