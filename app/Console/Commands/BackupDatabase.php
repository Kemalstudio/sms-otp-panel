<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Резервная копия базы.
 *
 * Потерять базу — значит потерять привязки телефонов: токен устройства
 * выдаётся ровно один раз и восстановлению не подлежит, каждый аппарат
 * придётся привязывать заново руками. Логи и ключи тоже уходят.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep= : Сколько копий оставить}';

    protected $description = 'Dump the database to storage/backups and rotate old copies';

    public function handle(): int
    {
        $directory = (string) config('gateway.backups.path');
        File::ensureDirectoryExists($directory);

        $connection = config('database.default');
        $stamp = now()->format('Y-m-d_H-i');

        $result = match ($connection) {
            'pgsql' => $this->dumpPostgres($directory, $stamp),
            'sqlite' => $this->copySqlite($directory, $stamp),
            default => null,
        };

        if ($result === null) {
            $this->error("Резервное копирование для соединения [{$connection}] не реализовано.");

            return self::FAILURE;
        }

        $this->info('Копия: '.$result.' ('.$this->humanSize(filesize($result)).')');

        $this->rotate($directory);

        return self::SUCCESS;
    }

    /**
     * pg_dump с хоста, а если его нет — из контейнера.
     *
     * На машинах, где Postgres поднят через docker compose, клиентских
     * утилит обычно не стоит, зато они есть внутри образа.
     */
    private function dumpPostgres(string $directory, string $stamp): ?string
    {
        $config = config('database.connections.pgsql');
        $target = $directory.DIRECTORY_SEPARATOR."otp-gateway_{$stamp}.sql";

        $hostDump = [
            'pg_dump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--no-password',
            '--clean',
            '--if-exists',
            $config['database'],
        ];

        $process = new Process($hostDump, env: ['PGPASSWORD' => $config['password']]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $container = (string) config('gateway.backups.docker_container');
            $this->line("pg_dump на хосте недоступен, пробую контейнер {$container}…");

            $process = new Process([
                'docker', 'exec', $container,
                'pg_dump', '-U', $config['username'], '--clean', '--if-exists', $config['database'],
            ]);
            $process->setTimeout(300);
            $process->run();
        }

        if (! $process->isSuccessful()) {
            $this->error('Не удалось снять дамп: '.trim($process->getErrorOutput()));

            return null;
        }

        // Пустой дамп — это тоже провал, просто молчаливый.
        $sql = $process->getOutput();

        if (mb_strlen($sql) < 100) {
            $this->error('Дамп подозрительно пуст, копия не сохранена.');

            return null;
        }

        File::put($target, $sql);

        return $target;
    }

    private function copySqlite(string $directory, string $stamp): ?string
    {
        $source = config('database.connections.sqlite.database');

        if (! is_file($source)) {
            $this->error("Файл базы не найден: {$source}");

            return null;
        }

        $target = $directory.DIRECTORY_SEPARATOR."otp-gateway_{$stamp}.sqlite";
        File::copy($source, $target);

        return $target;
    }

    /**
     * Удаляет лишние копии — но только после того, как новая уже легла на диск.
     */
    private function rotate(string $directory): void
    {
        $keep = (int) ($this->option('keep') ?? config('gateway.backups.keep'));

        if ($keep < 1) {
            return;
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'otp-gateway_'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $stale = $files->slice($keep);

        foreach ($stale as $file) {
            File::delete($file->getPathname());
        }

        if ($stale->isNotEmpty()) {
            $this->line('Удалено старых копий: '.$stale->count());
        }
    }

    private function humanSize(int $bytes): string
    {
        return $bytes > 1048576
            ? number_format($bytes / 1048576, 1, ',', ' ').' МБ'
            : number_format($bytes / 1024, 0, ',', ' ').' КБ';
    }
}
