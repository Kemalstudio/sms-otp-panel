<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpLog extends Model
{
    /** @use HasFactory<\Database\Factories\OtpLogFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'sent', 'delivered', 'failed', 'expired'];

    /**
     * Statuses a device is allowed to report back over the device API.
     */
    public const DEVICE_REPORTABLE_STATUSES = ['sent', 'failed'];

    public const LIFETIME_MINUTES = 5;

    public const MAX_VERIFY_ATTEMPTS = 5;

    protected $fillable = [
        'device_id',
        'phone',
        'code_hash',
        'code_encrypted',
        'status',
        'attempts',
        'expires_at',
    ];

    protected $hidden = [
        'code_hash',
        'code_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            // Reversible, unlike code_hash: the SMS text needs the digits back.
            'code_encrypted' => 'encrypted',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(
            $status && in_array($status, self::STATUSES, true),
            fn (Builder $q) => $q->where('status', $status)
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempts < self::MAX_VERIFY_ATTEMPTS;
    }

    public function attemptsLeft(): int
    {
        return max(0, self::MAX_VERIFY_ATTEMPTS - $this->attempts);
    }

    /**
     * Drops the reversible copy of the code once it has been handed to a device.
     */
    public function forgetPlainCode(): void
    {
        $this->forceFill(['code_encrypted' => null])->save();
    }

    public function markStatus(string $status): void
    {
        $this->forceFill(['status' => $status])->save();
    }

    /**
     * Phone with everything but the country code and the last four digits
     * replaced by X, e.g. "+993 XX XXX 4567".
     */
    public function getMaskedPhoneAttribute(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        if (strlen($digits) <= 4) {
            return str_repeat('X', max(0, strlen($digits) - 1)).substr($digits, -1);
        }

        $country = substr($digits, 0, max(1, strlen($digits) - 8));
        $last = substr($digits, -4);

        return sprintf('+%s XX XXX %s', $country, $last);
    }
}
