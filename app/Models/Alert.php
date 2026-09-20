<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Открытая или закрытая авария.
 *
 * Хранится в базе, а не только уходит письмом, по двум причинам: чтобы не
 * слать одно и то же каждую минуту и чтобы в панели было видно, что именно
 * сломано прямо сейчас.
 */
class Alert extends Model
{
    /** @use HasFactory<\Database\Factories\AlertFactory> */
    use HasFactory;

    public const TYPE_DEVICES_OFFLINE = 'devices.offline';

    public const TYPE_FAILURE_RATE = 'otp.failure_rate';

    public const TYPE_QUEUE_BACKLOG = 'queue.backlog';

    protected $fillable = [
        'project_id',
        'type',
        'message',
        'status',
        'started_at',
        'last_seen_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Человеческое название типа — одно на панель и на письмо. */
    public function getTitleAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEVICES_OFFLINE => 'Нет живых телефонов',
            self::TYPE_FAILURE_RATE => 'Много неудачных отправок',
            self::TYPE_QUEUE_BACKLOG => 'Очередь не разбирается',
            default => $this->type,
        };
    }
}
