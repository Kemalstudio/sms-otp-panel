<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PairingCode extends Model
{
    /** @use HasFactory<\Database\Factories\PairingCodeFactory> */
    use HasFactory;

    public const LIFETIME_MINUTES = 5;

    /**
     * Ambiguous glyphs (0/O, 1/I) are left out — the code gets typed by hand
     * when the camera cannot read the QR.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'code',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function issueFor(Project $project): self
    {
        return $project->pairingCodes()->create([
            'code' => self::generateUniqueCode(),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);
    }

    /**
     * Unused and not yet expired — the only state a phone may pair with.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }

    public function markUsed(): void
    {
        $this->forceFill(['used_at' => now()])->save();
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * What the phone reads out of the QR code.
     */
    public function qrPayload(): string
    {
        return json_encode([
            'pairing_code' => $this->code,
            'api_url' => rtrim((string) config('app.url'), '/'),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function generateUniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
