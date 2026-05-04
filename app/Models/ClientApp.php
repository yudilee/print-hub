<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ClientApp represents a registered third-party application authorized to use the Print Hub API.
 *
 * Each client app is issued a unique API key (X-API-Key header) that is hashed before storage.
 * The raw key is shown only once at creation time. Allowed origins restrict CORS for agent-side
 * access control.
 */
class ClientApp extends Model
{
    protected $fillable = [
        'name', 'api_key', 'is_active', 'last_used_at', 'allowed_origins', 'last_key_rotated_at',
        'webhook_events', 'webhook_retry_count', 'webhook_timeout', 'webhook_secret',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'is_active'           => 'boolean',
        'last_used_at'        => 'datetime',
        'last_key_rotated_at' => 'datetime',
        'allowed_origins'     => 'array',
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
     * Hash a raw API key for storage.
     */
    public static function hashKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    /**
     * Look up a ClientApp by its raw API key.
     */
    public static function findByKey(string $rawKey): ?self
    {
        return static::where('api_key', static::hashKey($rawKey))->first();
    }

    // ── Relationships ────────────────────────────────────────

    public function webhookDeliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
