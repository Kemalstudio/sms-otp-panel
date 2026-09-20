<?php

namespace App\Console\Commands;

use App\Models\OtpLog;
use App\Support\Webhooks\Webhooks;
use Illuminate\Console\Command;

/**
 * Moves OTPs nobody ever verified out of the live statuses.
 *
 * Without this they sit at `pending`/`sent` forever and the dashboard keeps
 * counting them as in-flight. It also drops the reversible copy of the code,
 * so an expired record stops being worth stealing.
 */
class SweepExpiredOtpLogs extends Command
{
    protected $signature = 'otp:sweep-expired';

    protected $description = 'Mark timed-out OTP logs as expired and forget their plaintext codes';

    public function handle(): int
    {
        /*
         * Идентификаторы собираются до обновления: массовый UPDATE не поднимает
         * событий модели, а истечение кода — ровно тот статус, о котором
         * интегратору важнее всего узнать. Берутся только логи проектов с
         * включёнными вебхуками, чтобы не тащить в память лишнее.
         */
        $notifiable = OtpLog::query()
            ->whereIn('status', ['pending', 'sent'])
            ->where('expires_at', '<=', now())
            ->whereHas('project', fn ($query) => $query
                ->whereNotNull('webhook_url')
                ->whereNotNull('webhook_secret'))
            ->pluck('id');

        $swept = OtpLog::query()
            ->whereIn('status', ['pending', 'sent'])
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'code_encrypted' => null,
                'updated_at' => now(),
            ]);

        // Codes of already resolved logs are of no use either.
        $scrubbed = OtpLog::query()
            ->whereNotNull('code_encrypted')
            ->whereIn('status', ['delivered', 'failed', 'expired'])
            ->update(['code_encrypted' => null, 'updated_at' => now()]);

        OtpLog::with(['project', 'device'])
            ->whereIn('id', $notifiable)
            ->each(fn (OtpLog $otpLog) => Webhooks::sendOtpEvent($otpLog));

        $this->info("Expired {$swept} OTP log(s), scrubbed {$scrubbed} leftover code(s).");

        return self::SUCCESS;
    }
}
