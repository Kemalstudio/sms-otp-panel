<?php

namespace App\Jobs;

use App\Exceptions\FcmDeliveryException;
use App\Facades\Fcm;
use App\Models\Device;
use App\Models\OtpLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands one OTP to the phone that will actually send the SMS.
 *
 * The phone receives a data-only message and replies later over
 * /api/v1/devices/report-status, which is what moves the log to sent/failed.
 *
 * A handset that cannot be reached does not sink the OTP: the job hands the
 * message to the next device in the round-robin and only gives up once the
 * whole pool has been tried.
 */
class SendOtpViaFcmJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $triedDeviceIds  handsets already attempted for this OTP
     */
    public function __construct(
        public int $otpLogId,
        public int $deviceId,
        public array $triedDeviceIds = [],
    ) {
    }

    public function handle(): void
    {
        $otpLog = OtpLog::find($this->otpLogId);
        $device = Device::find($this->deviceId);

        if (! $otpLog) {
            return;
        }

        // Already resolved elsewhere (expired sweep, device report, retry).
        if ($otpLog->status !== 'pending') {
            return;
        }

        if ($otpLog->isExpired()) {
            $otpLog->forceFill(['status' => 'expired', 'code_encrypted' => null])->save();

            return;
        }

        $code = $otpLog->code_encrypted;

        if (blank($code)) {
            $this->giveUp($otpLog, 'the reversible copy of the code is gone');

            return;
        }

        if (! $device || blank($device->fcm_token)) {
            $this->failover($otpLog, 'device is missing an fcm token');

            return;
        }

        try {
            Fcm::sendData($device->fcm_token, [
                'type' => 'send_sms',
                'otp_id' => (string) $otpLog->id,
                'phone' => (string) $otpLog->phone,
                'message' => "Ваш код: {$code}",
            ]);
        } catch (FcmDeliveryException $e) {
            $this->failover($otpLog, $e->getMessage());

            return;
        }

        // The plaintext copy exists only to build this one SMS body.
        $otpLog->forgetPlainCode();
    }

    /**
     * Queue-level failure (all retries exhausted, worker crash).
     */
    public function failed(?Throwable $e): void
    {
        $otpLog = OtpLog::find($this->otpLogId);

        if ($otpLog && $otpLog->status === 'pending') {
            $otpLog->forceFill(['status' => 'failed', 'code_encrypted' => null])->save();
        }
    }

    /**
     * Re-queues the OTP against the next untried handset, or fails it once the
     * pool is exhausted.
     */
    private function failover(OtpLog $otpLog, string $reason): void
    {
        $tried = [...$this->triedDeviceIds, $this->deviceId];

        $next = $otpLog->project->nextDispatchableDevice($tried);

        if (! $next) {
            $this->giveUp($otpLog, $reason.' (no other device left to try)');

            return;
        }

        Log::info('OTP delivery retried on another device', [
            'otp_log_id' => $otpLog->id,
            'failed_device_id' => $this->deviceId,
            'next_device_id' => $next->id,
            'reason' => $reason,
        ]);

        $otpLog->forceFill(['device_id' => $next->id])->save();
        $next->markDispatched();

        self::dispatch($otpLog->id, $next->id, $tried);
    }

    private function giveUp(OtpLog $otpLog, string $reason): void
    {
        Log::warning('OTP delivery failed', [
            'otp_log_id' => $otpLog->id,
            'device_id' => $this->deviceId,
            'reason' => $reason,
        ]);

        $otpLog->forceFill(['status' => 'failed', 'code_encrypted' => null])->save();
    }
}
