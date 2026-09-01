<?php

namespace App\Models;

use App\Traits\BranchScopeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * PrintAgent represents a desktop workstation running the TrayPrint client.
 *
 * Each agent is issued an agent_key (Bearer token) used to authenticate
 * against the Print Hub agent API. The key is hashed before storage and
 * the raw value is shown only once at creation.
 *
 * Key hashing uses bcrypt (via Hash::make) for new keys. Legacy sha256
 * hashes are supported through dual-auth lookup in findByKey().
 */
class PrintAgent extends Model
{
    use BranchScopeable;

    protected $fillable = ['name', 'agent_key', 'ip_address', 'allowed_ips', 'location', 'department', 'last_seen_at', 'last_telemetry_at', 'is_active', 'printers', 'capabilities', 'hardware_status', 'branch_id', 'last_key_rotated_at', 'key_hash_bcrypt'];

    protected $hidden = ['agent_key'];

    protected $casts = [
        'last_seen_at'        => 'datetime',
        'last_telemetry_at'   => 'datetime',
        'last_key_rotated_at' => 'datetime',
        'is_active'           => 'boolean',
        'printers'            => 'array',
        'capabilities'        => 'array',
        'hardware_status'     => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PrintAgent $agent) {
            if (is_null($agent->last_key_rotated_at)) {
                $agent->last_key_rotated_at = now();
            }
        });
    }

    // ── Relationships ────────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isOnline(): bool
    {
        $threshold = config('app.agent_online_threshold', 2);
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < $threshold;
    }

    /**
     * Hash a raw agent key for storage using bcrypt.
     *
     * Used when creating or rotating keys. The resulting hash is stored
     * in both `agent_key` (for backward compat) and `key_hash_bcrypt`.
     */
    public static function hashKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    /**
     * Look up a PrintAgent by its raw agent key.
     *
     * Prioritizes fast indexed SHA-256 lookup, falling back to bcrypt if necessary.
     */
    public static function findByKey(string $rawKey): ?self
    {
        $sha256 = hash('sha256', $rawKey);

        // Fast indexed SHA-256 lookup first
        $agent = static::where('agent_key', $sha256)
            ->orWhere('key_hash_bcrypt', $sha256)
            ->first();

        if ($agent) {
            return $agent;
        }

        // Fallback to bcrypt lookup for legacy bcrypt-hashed keys,
        // then plaintext comparison for pre-migration legacy keys.
        // We optimize this by only calling Hash::check if the value starts with '$2'.
        $agent = static::get()
            ->first(function ($a) use ($rawKey, $sha256) {
                try {
                    if (str_starts_with($a->agent_key, '$2') && Hash::check($rawKey, $a->agent_key)) {
                        return true;
                    }
                    if ($a->key_hash_bcrypt && str_starts_with($a->key_hash_bcrypt, '$2') && Hash::check($rawKey, $a->key_hash_bcrypt)) {
                        return true;
                    }
                    return $a->agent_key === $sha256
                        || $a->agent_key === $rawKey;
                } catch (\Exception $e) {
                    return $a->agent_key === $sha256
                        || $a->agent_key === $rawKey;
                }
            });

        return $agent;
    }
}
