<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    /** @use HasFactory<\Database\Factories\ApiKeyFactory> */
    use HasFactory;

    public const PREFIX = 'sk_live_';

    protected $fillable = [
        'key_hash',
        'key_prefix',
        'last_used_at',
        'revoked_at',
    ];

    /**
     * The plaintext key. Only ever populated in the request that created it,
     * never loaded from the database.
     */
    protected ?string $plainTextKey = null;

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Build a new key for a project. Returns the persisted model with the
     * plaintext available once via plainTextKey(); the database only ever
     * receives the sha256 hash.
     */
    public static function generateFor(Project $project): self
    {
        $plain = self::PREFIX.Str::random(32);

        $key = $project->apiKeys()->create([
            'key_hash' => self::hashKey($plain),
            'key_prefix' => substr($plain, 0, strlen(self::PREFIX) + 8),
        ]);

        $key->plainTextKey = $plain;

        return $key;
    }

    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function plainTextKey(): ?string
    {
        return $this->plainTextKey;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function getStatusAttribute(): string
    {
        return $this->isRevoked() ? 'revoked' : 'active';
    }

    public function revoke(): void
    {
        if (! $this->isRevoked()) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }
}
