<?php

namespace App\Support\Stats;

use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Отправки по часам и по дням.
 *
 * Цифра «отправлено сегодня: 3» не отвечает на вопрос «когда именно всё
 * сломалось». График отвечает: провал видно сразу, и видно, совпал ли он с
 * моментом, когда телефон ушёл офлайн.
 */
class OtpTrend
{
    /**
     * Почасовая разбивка за последние сутки.
     *
     * @return Collection<int, array{label: string, at: Carbon, sent: int, failed: int, total: int}>
     */
    public static function hourly(Project $project, int $hours = 24): Collection
    {
        $since = now()->subHours($hours - 1)->startOfHour();

        $rows = self::rowsSince($project, $since)
            ->groupBy(fn (OtpLog $log) => $log->created_at->format('Y-m-d H'));

        return collect(range(0, $hours - 1))->map(function (int $offset) use ($since, $rows) {
            $at = $since->copy()->addHours($offset);

            return self::bucket($rows->get($at->format('Y-m-d H'), collect()), $at, $at->format('H:i'));
        });
    }

    /**
     * По дням — чтобы понимать, растёт ли нагрузка.
     *
     * @return Collection<int, array{label: string, at: Carbon, sent: int, failed: int, total: int}>
     */
    public static function daily(Project $project, int $days = 14): Collection
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $rows = self::rowsSince($project, $since)
            ->groupBy(fn (OtpLog $log) => $log->created_at->format('Y-m-d'));

        return collect(range(0, $days - 1))->map(function (int $offset) use ($since, $rows) {
            $at = $since->copy()->addDays($offset);

            return self::bucket($rows->get($at->format('Y-m-d'), collect()), $at, $at->format('d.m'));
        });
    }

    /**
     * @return Collection<int, OtpLog>
     */
    private static function rowsSince(Project $project, Carbon $since): Collection
    {
        // Только два поля: строить график на полных моделях с шифрованными
        // полями незачем.
        return $project->otpLogs()
            ->where('created_at', '>=', $since)
            ->get(['id', 'status', 'created_at']);
    }

    /**
     * @param  Collection<int, OtpLog>  $bucket
     * @return array{label: string, at: Carbon, sent: int, failed: int, total: int}
     */
    private static function bucket(Collection $bucket, Carbon $at, string $label): array
    {
        // `expired` в неуспешные не идёт: код, который клиент не ввёл, — его
        // решение, а не сбой шлюза.
        $failed = $bucket->where('status', 'failed')->count();

        return [
            'label' => $label,
            'at' => $at,
            'sent' => $bucket->count() - $failed,
            'failed' => $failed,
            'total' => $bucket->count(),
        ];
    }
}
