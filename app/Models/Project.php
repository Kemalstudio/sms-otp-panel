<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'webhook_url',
    ];

    /**
     * Секрет подписи скрыт от сериализации: в JSON проекта ему не место,
     * показывается он только на своей странице в панели.
     */
    protected $hidden = [
        'webhook_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function otpLogs(): HasMany
    {
        return $this->hasMany(OtpLog::class);
    }

    public function pairingCodes(): HasMany
    {
        return $this->hasMany(PairingCode::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /** Вебхуки включены только когда есть и адрес, и чем подписывать. */
    public function hasWebhook(): bool
    {
        return filled($this->webhook_url) && filled($this->webhook_secret);
    }

    /**
     * Новый секрет подписи. Старый перестаёт работать сразу — это и есть
     * способ отозвать доступ, если секрет утёк.
     */
    public function rotateWebhookSecret(): string
    {
        $secret = 'whsec_'.Str::random(48);

        $this->forceFill(['webhook_secret' => $secret])->save();

        return $secret;
    }

    /**
     * Devices that reported in within the online window.
     */
    public function onlineDevicesCount(): int
    {
        return $this->devices()->online()->count();
    }

    /**
     * OTP messages created today, in the application timezone.
     */
    public function otpSentTodayCount(): int
    {
        return $this->otpLogs()->whereDate('created_at', now()->toDateString())->count();
    }

    /**
     * Телефон, которому достанется следующий код: активный, на связи, с
     * незабитой пропускной способностью и дольше всех не отправлявший
     * (round-robin).
     *
     * @param  list<int>  $excludeDeviceIds  телефоны, уже опробованные для этого кода
     * @param  string|null  $from  номер отправителя, если вызывающий его зафиксировал
     */
    public function nextDispatchableDevice(array $excludeDeviceIds = [], ?string $from = null): ?Device
    {
        return $this->dispatchableDevices($excludeDeviceIds, $from)
            ->first(fn (Device $device) => $device->hasCapacity());
    }

    /**
     * Весь пул, годный к отправке, в порядке round-robin.
     *
     * Отдаётся целиком, чтобы вызывающий мог отличить «телефонов нет вообще»
     * от «все упёрлись в свой лимит»: это разные ответы API и разные действия
     * для интегратора.
     *
     * @param  list<int>  $excludeDeviceIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Device>
     */
    public function dispatchableDevices(array $excludeDeviceIds = [], ?string $from = null): Collection
    {
        return $this->devices()
            ->dispatchable()
            ->when($excludeDeviceIds, fn ($q) => $q->whereNotIn('id', $excludeDeviceIds))
            ->when($from, fn ($q) => $q->where('phone_number', $from))
            ->orderBy('last_dispatched_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Сколько кодов в минуту проект способен вывезти сейчас — сумма лимитов
     * телефонов, которые на связи.
     */
    public function throughputPerMinute(): int
    {
        return (int) $this->devices()->dispatchable()->sum('throughput_per_minute');
    }
}
