<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * ClientApp represents a registered third-party application authorized to use the Print Hub API.
 *
 * Each client app is issued a unique API key (X-API-Key header) that is hashed before storage.
 * The raw key is shown only once at creation time. Allowed origins restrict CORS for agent-side
 * access control.
 *
 * Key hashing uses bcrypt (via Hash::make) for new keys. Legacy sha256
 * hashes are supported through dual-auth lookup in findByKey().
 */
class ClientApp extends Model
{
    protected $fillable = [
        'name', 'api_key', 'is_active', 'last_used_at', 'allowed_origins', 'allowed_ips', 'last_key_rotated_at',
        'webhook_events', 'webhook_retry_count', 'webhook_timeout', 'webhook_secret', 'key_hash_bcrypt',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'is_active'           => 'boolean',
        'last_used_at'        => 'datetime',
        'last_key_rotated_at' => 'datetime',
        'allowed_origins'     => 'array',
        'allowed_ips'         => 'array',
        'webhook_events'      => 'array',
        'webhook_retry_count' => 'integer',
        'webhook_timeout'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClientApp $app) {
            if (is_null($app->last_key_rotated_at)) {
                $app->last_key_rotated_at = now();
            }
        });
    }

    /**
     * Hash a raw API key for storage using bcrypt.
     *
     * Used when creating or rotating keys. The resulting hash is stored
     * in both `api_key` (for backward compat) and `key_hash_bcrypt`.
     */
    public static function hashKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    /**
     * Look up a ClientApp by its raw API key.
     *
     * Prioritizes fast indexed SHA-256 lookup, falling back to bcrypt if necessary.
     */
    public static function findByKey(string $rawKey): ?self
    {
        $sha256 = hash('sha256', $rawKey);

        // Fast indexed SHA-256 lookup first
        $app = static::where('api_key', $sha256)
            ->orWhere('key_hash_bcrypt', $sha256)
            ->first();

        if ($app) {
            return $app;
        }

        // Fallback to bcrypt lookup for legacy bcrypt-hashed keys
        // We optimize this by only calling Hash::check if the value starts with '$2'.
        $app = static::get()
            ->first(function ($a) use ($rawKey, $sha256) {
                try {
                    if (str_starts_with($a->api_key, '$2') && Hash::check($rawKey, $a->api_key)) {
                        return true;
                    }
                    if ($a->key_hash_bcrypt && str_starts_with($a->key_hash_bcrypt, '$2') && Hash::check($rawKey, $a->key_hash_bcrypt)) {
                        return true;
                    }
                    return $a->api_key === $sha256;
                } catch (\Exception $e) {
                    return $a->api_key === $sha256;
                }
            });

        return $app;
    }

    // ── Relationships ────────────────────────────────────────

    public function webhookDeliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
