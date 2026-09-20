<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory;

    /**
     * A device is considered offline when it has not reported in
     * for longer than this many minutes.
     */
    public const ONLINE_THRESHOLD_MINUTES = 5;

    /**
     * Сколько SMS в минуту телефон отправляет по умолчанию.
     *
     * Взято с запасом вниз: одна отправка занимает 2-5 секунд, а Android без
     * подтверждения пользователя режет фоновому приложению отправку примерно
     * на 30 сообщениях за полчаса. Оператор может поднять лимит в панели, если
     * его аппарат и оператор связи это тянут.
     */
    public const DEFAULT_THROUGHPUT_PER_MINUTE = 10;

    public const MAX_THROUGHPUT_PER_MINUTE = 60;

    protected $fillable = [
        'name',
        'phone_number',
        'status',
        'throughput_per_minute',
        'last_seen_at',
        'last_dispatched_at',
        'battery_level',
        'fcm_token',
        'token_hash',
    ];

    protected $hidden = [
        'token_hash',
        'fcm_token',
    ];

    /**
     * The device token. Only populated in the pairing response, never loaded
     * from the database.
     */
    protected ?string $plainTextToken = null;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_dispatched_at' => 'datetime',
            'throughput_per_minute' => 'integer',
            'battery_level' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function otpLogs(): HasMany
    {
        return $this->hasMany(OtpLog::class);
    }

    /**
     * Only devices seen inside the online window.
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES));
    }

    /**
     * Devices eligible to deliver an OTP: marked active and still reporting in.
     */
    public function scopeDispatchable(Builder $query): Builder
    {
        return $query->where('status', 'active')->online();
    }

    /**
     * Сколько кодов уже ушло на этот телефон за последнюю минуту.
     *
     * Считается по otp_logs, а не по счётчику в памяти: перезапуск воркера не
     * должен обнулять пропускную способность и выпускать залп на телефон.
     */
    public function dispatchedLastMinute(): int
    {
        return $this->otpLogs()
            ->where('created_at', '>=', now()->subMinute())
            ->count();
    }

    /**
     * Есть ли у телефона свободная ёмкость прямо сейчас.
     */
    public function hasCapacity(): bool
    {
        return $this->dispatchedLastMinute() < $this->throughput_per_minute;
    }

    /**
     * Через сколько секунд у телефона освободится ёмкость.
     *
     * Окно скользящее: ждать надо не «до конца минуты», а пока из него не
     * выпадет отправка, занявшая последний слот.
     */
    public function secondsUntilCapacity(): int
    {
        if ($this->hasCapacity()) {
            return 0;
        }

        $slotTaken = $this->otpLogs()
            ->where('created_at', '>=', now()->subMinute())
            ->orderByDesc('created_at')
            ->skip($this->throughput_per_minute - 1)
            ->take(1)
            ->value('created_at');

        if (! $slotTaken) {
            return 0;
        }

        $age = Carbon::parse($slotTaken)->diffInSeconds(now());

        return (int) max(1, 60 - $age);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES));
    }

    /**
     * Status derived from last_seen_at rather than the stored column,
     * so a device that silently dropped off shows as inactive.
     */
    public function getEffectiveStatusAttribute(): string
    {
        return $this->isOnline() ? 'active' : 'inactive';
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Issues a fresh device token, stores only its hash, and keeps the
     * plaintext on the instance for this request only.
     */
    public function issueToken(): string
    {
        $plain = Str::random(64);

        $this->forceFill(['token_hash' => self::hashToken($plain)])->save();
        $this->plainTextToken = $plain;

        return $plain;
    }

    public function plainTextToken(): ?string
    {
        return $this->plainTextToken;
    }

    public function markSeen(): void
    {
        $this->forceFill([
            'status' => 'active',
            'last_seen_at' => now(),
        ])->save();
    }

    public function markDispatched(): void
    {
        $this->forceFill(['last_dispatched_at' => now()])->save();
    }
}
