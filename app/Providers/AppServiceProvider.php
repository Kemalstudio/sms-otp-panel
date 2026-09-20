<?php

namespace App\Providers;

use App\Contracts\FcmSender;
use App\Models\OtpLog;
use App\Observers\OtpLogObserver;
use App\Services\Fcm\FirebaseFcmSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolved lazily; the Firebase SDK itself is only built on first send,
        // and tests replace this binding through Fcm::fake().
        $this->app->singleton(FcmSender::class, fn () => new FirebaseFcmSender(
            config('firebase.credentials')
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Панель русскоязычная, поэтому «1 day ago» в таблицах читается как баг.
         * Локаль приложения при этом остаётся английской: переводов валидации
         * на русский в проекте нет, и переключение сломало бы сообщения об
         * ошибках форм.
         */
        Carbon::setLocale('ru');
        CarbonImmutable::setLocale('ru');

        // События вебхуков поднимаются на переходе статуса, где бы он ни
        // произошёл: verify, отчёт телефона, failover.
        OtpLog::observe(OtpLogObserver::class);
    }
}
