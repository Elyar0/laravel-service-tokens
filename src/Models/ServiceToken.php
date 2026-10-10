<?php

namespace Elyar\ServiceTokens\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ServiceToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'active' => 'boolean',
        'super' => 'boolean',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(ServiceTokenPermission::class);
    }

    /**
     * Secrets are 256 random bits, so a fast hash is as safe as bcrypt here
     * and keeps per-request verification cheap.
     */
    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function matchesSecret(string $secret): bool
    {
        return hash_equals($this->token_hash, self::hashSecret($secret));
    }

    /**
     * Granted method@uri names that match none of the given endpoints,
     * e.g. after a route was renamed or removed.
     *
     * @param string[] $endpoints
     * @return Collection<int, string>
     */
    public function staleGrants(array $endpoints): Collection
    {
        return $this->permissions->pluck('name')->diff($endpoints)->values();
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function revoke(): void
    {
        $this->revoked_at = now();
        $this->save();
    }
}
