<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

/**
 * Flips devices that stopped sending heartbeats to `inactive`.
 *
 * Dispatch already ignores them (Device::scopeDispatchable filters on
 * last_seen_at), but the stored column would otherwise claim "active"
 * forever for a handset that was switched off weeks ago.
 */
class SweepStaleDevices extends Command
{
    protected $signature = 'devices:sweep-stale';

    protected $description = 'Mark devices that stopped reporting in as inactive';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(Device::ONLINE_THRESHOLD_MINUTES);

        $swept = Device::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cutoff))
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        $this->info("Marked {$swept} device(s) inactive.");

        return self::SUCCESS;
    }
}
