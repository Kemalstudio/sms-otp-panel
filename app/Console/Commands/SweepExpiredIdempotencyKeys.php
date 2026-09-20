<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

/**
 * Убирает запомненные ответы, у которых вышел срок.
 *
 * Без этого таблица растёт вечно, а вместе с ней — тела ответов, в которых
 * лежат номера получателей.
 */
class SweepExpiredIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:sweep-expired';

    protected $description = 'Delete idempotency records past their TTL';

    public function handle(): int
    {
        $deleted = IdempotencyKey::query()
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Deleted {$deleted} expired idempotency record(s).");

        return self::SUCCESS;
    }
}
