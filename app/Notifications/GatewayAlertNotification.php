<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Письмо об аварии или о её устранении.
 *
 * Намеренно НЕ ShouldQueue: одна из аварий — «очередь не разбирается», и
 * уведомление о ней не должно ждать в той же очереди.
 */
class GatewayAlertNotification extends Notification
{
    public function __construct(
        public Alert $alert,
        public bool $opened = true,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->alert->project;
        $scope = $project ? 'Проект «'.$project->name.'»' : 'Шлюз';

        if (! $this->opened) {
            return (new MailMessage)
                ->subject('Восстановлено: '.$this->alert->title)
                ->line($scope.': «'.$this->alert->title.'» больше не воспроизводится.')
                ->line('Авария длилась с '.$this->alert->started_at->format('d.m.Y H:i').'.');
        }

        $mail = (new MailMessage)
            ->error()
            ->subject('Авария шлюза: '.$this->alert->title)
            ->line($scope)
            ->line($this->alert->message);

        if ($project) {
            $mail->action('Открыть проект', route('projects.show', $project));
        }

        return $mail->line('Пока авария не устранена, повторных писем не будет.');
    }
}
