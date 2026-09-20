<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Одна попытка доставить событие на webhook_url проекта.
 *
 * Строка живёт от постановки в очередь до успеха или исчерпания ретраев, и
 * именно она отвечает на вопрос «вы точно нам присылали?».
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'otp_log_id',
        'event',
        'url',
        'status',
        'attempts',
        'response_status',
        'error',
        'payload',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function otpLog(): BelongsTo
    {
        return $this->belongsTo(OtpLog::class);
    }

    public function markDelivered(int $responseStatus): void
    {
        $this->forceFill([
            'status' => 'delivered',
            'response_status' => $responseStatus,
            'error' => null,
            'delivered_at' => now(),
        ])->save();
    }

    public function markFailed(?int $responseStatus, string $error, bool $final): void
    {
        $this->forceFill([
            // Пока ретраи не исчерпаны, доставка остаётся pending: «failed»
            // должно означать «больше не придёт», а не «пока не дошло».
            'status' => $final ? 'failed' : 'pending',
            'response_status' => $responseStatus,
            'error' => mb_substr($error, 0, 500),
        ])->save();
    }
}
