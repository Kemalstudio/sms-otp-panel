<?php

namespace App\Observers;

use App\Models\OtpLog;
use App\Support\Webhooks\Webhooks;

/**
 * Единственное место, где рождаются события о коде.
 *
 * Наблюдатель, а не вызовы по коду: статус меняют и verify, и отчёт телефона, и
 * failover в задаче отправки — рассыпать по ним одинаковый вызов значило бы
 * однажды забыть про одну ветку.
 */
class OtpLogObserver
{
    public function updated(OtpLog $otpLog): void
    {
        if (! $otpLog->wasChanged('status')) {
            return;
        }

        Webhooks::sendOtpEvent($otpLog);
    }
}
