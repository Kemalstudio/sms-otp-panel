<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Maintenance schedule
|--------------------------------------------------------------------------
|
| Both sweeps are cheap single UPDATE statements and are safe to run every
| minute. They need `php artisan schedule:work` (dev) or a one-line system
| cron calling `schedule:run` (production) — see the README.
|
*/

Schedule::command('otp:sweep-expired')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('devices:sweep-stale')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Проверка здоровья: пул телефонов, доля неудачных отправок, живость очереди.
 * Раз в минуту — потому что простой шлюза измеряется минутами, а не часами.
 */
Schedule::command('gateway:check-health')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Копия базы раз в сутки. Потеря базы — это потеря привязок телефонов: токен
 * устройства выдаётся один раз, и каждый аппарат придётся привязывать заново.
 */
Schedule::command('backup:database')
    ->dailyAt('03:30')
    ->withoutOverlapping();

/*
 * Запомненные ответы живут сутки: чистить их чаще раза в час незачем, а не
 * чистить вовсе — значит вечно хранить номера получателей в телах ответов.
 */
Schedule::command('idempotency:sweep-expired')
    ->hourly()
    ->withoutOverlapping();
