<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Project;
use App\Support\Alerts\AlertManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Замечает поломки раньше, чем их заметит клиент.
 *
 * Шлюз умирает тихо: телефон ушёл в сон, воркер не запустился после
 * перезагрузки, оператор начал резать SMS. Ни одно из этих состояний не бросает
 * исключений — их видно только по данным, и только если смотреть.
 */
class CheckGatewayHealth extends Command
{
    protected $signature = 'gateway:check-health';

    protected $description = 'Raise alerts for offline device pools, failing sends and a stalled queue';

    public function handle(AlertManager $alerts): int
    {
        if (! config('gateway.alerts.enabled')) {
            $this->info('Алёрты выключены (GATEWAY_ALERTS_ENABLED=false).');

            return self::SUCCESS;
        }

        $this->checkQueue($alerts);

        Project::query()
            ->with('user')
            ->each(function (Project $project) use ($alerts) {
                $this->checkDevicePool($alerts, $project);
                $this->checkFailureRate($alerts, $project);
            });

        $active = Alert::query()->active()->count();
        $this->info("Проверка завершена. Открытых аварий: {$active}.");

        return self::SUCCESS;
    }

    /**
     * Пул телефонов. Проект без единого устройства не «сломан» — он просто ещё
     * не настроен, будить за это некого.
     */
    private function checkDevicePool(AlertManager $alerts, Project $project): void
    {
        if ($project->devices()->count() === 0) {
            return;
        }

        $threshold = (int) config('gateway.alerts.offline_minutes');
        $lastSeen = $project->devices()->max('last_seen_at');

        $stale = $lastSeen === null || now()->diffInMinutes($lastSeen, absolute: true) >= $threshold;

        if (! $stale) {
            $alerts->clear(Alert::TYPE_DEVICES_OFFLINE, $project);

            return;
        }

        $since = $lastSeen === null
            ? 'ни один телефон ни разу не выходил на связь'
            : 'последний обмен был '.Carbon::parse($lastSeen)->diffForHumans();

        $alerts->raise(
            Alert::TYPE_DEVICES_OFFLINE,
            "Больше {$threshold} мин нет ни одного телефона на связи: {$since}. ".
            'Запросы кодов сейчас получают 503.',
            $project,
        );
    }

    /**
     * Доля неудачных отправок за час.
     *
     * Считаются только `failed` — SMS, которую не удалось отдать оператору.
     * `expired` сюда не входит: код, который клиент просто не ввёл, — это его
     * решение, а не поломка шлюза.
     */
    private function checkFailureRate(AlertManager $alerts, Project $project): void
    {
        $minimum = (int) config('gateway.alerts.failure_rate_minimum');
        $limit = (int) config('gateway.alerts.failure_rate_percent');

        $window = $project->otpLogs()->where('created_at', '>=', now()->subHour());

        $total = (clone $window)->count();

        if ($total < $minimum) {
            // Две ошибки из трёх попыток в тихую ночь — не авария.
            $alerts->clear(Alert::TYPE_FAILURE_RATE, $project);

            return;
        }

        $failed = (clone $window)->where('status', 'failed')->count();
        $share = (int) round($failed * 100 / $total);

        if ($share <= $limit) {
            $alerts->clear(Alert::TYPE_FAILURE_RATE, $project);

            return;
        }

        $alerts->raise(
            Alert::TYPE_FAILURE_RATE,
            "За последний час не отправлено {$failed} из {$total} кодов ({$share}%). ".
            'Проверьте баланс SIM, сигнал и разрешения на телефонах.',
            $project,
        );
    }

    /**
     * Очередь. Если задача лежит готовой дольше порога, значит воркера нет —
     * и ни один код никуда не уедет, сколько бы телефонов ни было онлайн.
     */
    private function checkQueue(AlertManager $alerts): void
    {
        // Заглянуть внутрь мы умеем только в database-очередь; для redis это
        // работа внешнего мониторинга, и врать про «всё хорошо» не будем.
        if (config('queue.default') !== 'database') {
            return;
        }

        $threshold = (int) config('gateway.alerts.queue_backlog_minutes');

        $oldest = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->subMinutes($threshold)->getTimestamp())
            ->min('available_at');

        if ($oldest === null) {
            $alerts->clear(Alert::TYPE_QUEUE_BACKLOG);

            return;
        }

        $waiting = DB::table('jobs')->count();
        $minutes = (int) round((now()->getTimestamp() - (int) $oldest) / 60);

        $alerts->raise(
            Alert::TYPE_QUEUE_BACKLOG,
            "Задача ждёт исполнения {$minutes} мин, в очереди {$waiting} шт. ".
            'Похоже, queue:work не запущен — коды и вебхуки стоят.',
        );
    }
}
