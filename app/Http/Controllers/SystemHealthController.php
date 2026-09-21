<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Один экран: работает ли инфраструктура под шлюзом.
 *
 * Алёрты сообщают о поломке постфактум и только владельцу проекта. Эта
 * страница отвечает на вопрос «всё ли в порядке прямо сейчас» — и,
 * в отличие от алёртов, показывает то, что ещё не сломалось, но уже близко.
 */
class SystemHealthController extends Controller
{
    public function __invoke(): View
    {
        return view('system.health', [
            'checks' => [
                $this->database(),
                $this->cache(),
                $this->queue(),
                $this->scheduler(),
                $this->webhooks(),
                $this->backups(),
            ],
            'alerts' => Alert::with('project')->latest('started_at')->take(15)->get(),
            'devices' => Device::with('project')->get(),
        ]);
    }

    /**
     * @return array{name: string, state: string, value: string, hint: string}
     */
    private function database(): array
    {
        try {
            $started = microtime(true);
            DB::connection()->select('select 1');
            $ms = (int) round((microtime(true) - $started) * 1000);

            return $this->ok(
                'База данных',
                config('database.default').' · '.$ms.' мс',
                'Соединение живо, запросы проходят.',
            );
        } catch (Throwable $e) {
            return $this->fail('База данных', 'нет соединения', $e->getMessage());
        }
    }

    private function cache(): array
    {
        $driver = config('cache.default');

        if ($driver !== 'redis') {
            return $this->warn('Кэш', $driver, 'Redis не используется — это рабочий вариант, но медленнее.');
        }

        try {
            $pong = Redis::connection()->ping();

            return $this->ok('Redis', (string) $pong, 'Кэш и очередь отвечают.');
        } catch (Throwable $e) {
            return $this->fail('Redis', 'не отвечает', $e->getMessage());
        }
    }

    /**
     * Очередь — самое опасное место: её остановку не видно ниоткуда, кроме
     * того, что коды перестают уходить.
     */
    private function queue(): array
    {
        $connection = config('queue.default');

        if ($connection === 'redis') {
            try {
                $waiting = Redis::connection()->llen('queues:default');

                return $waiting > 50
                    ? $this->warn('Очередь', $waiting.' задач ждут', 'Воркер не успевает или остановлен.')
                    : $this->ok('Очередь', 'redis · '.$waiting.' в ожидании', 'Задачи разбираются.');
            } catch (Throwable $e) {
                return $this->fail('Очередь', 'недоступна', $e->getMessage());
            }
        }

        $waiting = DB::table('jobs')->count();

        return $waiting > 50
            ? $this->warn('Очередь', $waiting.' задач ждут', 'Похоже, queue:work не запущен.')
            : $this->ok('Очередь', $connection.' · '.$waiting.' в ожидании', 'Задачи разбираются.');
    }

    /**
     * Планировщик виден только по следу: последняя проверка здоровья.
     */
    private function scheduler(): array
    {
        $lastAlertTouch = Alert::max('last_seen_at');
        $marker = Cache::get('gateway:last-health-check');

        $last = $marker ?? $lastAlertTouch;

        if ($last === null) {
            return $this->warn(
                'Планировщик',
                'следов нет',
                'Ни одной проверки здоровья не отработало. Запустите schedule:work.',
            );
        }

        $minutes = now()->diffInMinutes($last, absolute: true);

        return $minutes <= 5
            ? $this->ok('Планировщик', 'проверка '.$minutes.' мин назад', 'Уборки и алёрты работают.')
            : $this->fail('Планировщик', 'молчит '.$minutes.' мин', 'Уборки просрочек и алёрты не работают.');
    }

    private function webhooks(): array
    {
        $failed = WebhookDelivery::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $failed === 0
            ? $this->ok('Вебхуки', 'без провалов за сутки', 'Все события доставлены.')
            : $this->warn('Вебхуки', $failed.' не доставлено', 'Приёмник клиента отвергает или не отвечает.');
    }

    private function backups(): array
    {
        $directory = (string) config('gateway.backups.path');

        if (! is_dir($directory)) {
            return $this->fail('Резервные копии', 'нет', 'Каталог копий не создан.');
        }

        $latest = collect(File::files($directory))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'otp-gateway_'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->first();

        if (! $latest) {
            return $this->fail('Резервные копии', 'ни одной', 'Потеря базы = потеря всех привязок телефонов.');
        }

        $hours = (int) round((time() - $latest->getMTime()) / 3600);

        return $hours <= 36
            ? $this->ok('Резервные копии', $hours.' ч назад', 'Последняя копия свежая.')
            : $this->warn('Резервные копии', $hours.' ч назад', 'Копия устарела — проверьте планировщик.');
    }

    private function ok(string $name, string $value, string $hint): array
    {
        return ['name' => $name, 'state' => 'ok', 'value' => $value, 'hint' => $hint];
    }

    private function warn(string $name, string $value, string $hint): array
    {
        return ['name' => $name, 'state' => 'warn', 'value' => $value, 'hint' => $hint];
    }

    private function fail(string $name, string $value, string $hint): array
    {
        return ['name' => $name, 'state' => 'fail', 'value' => $value, 'hint' => $hint];
    }

    /** Отметка, по которой видно, что планировщик жив. */
    public static function markHealthCheck(): void
    {
        Cache::put('gateway:last-health-check', now(), now()->addDay());
    }
}
