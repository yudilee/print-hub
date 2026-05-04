<?php

namespace App\Models;

use App\Traits\BranchScopeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PrintAgent represents a desktop workstation running the TrayPrint client.
 *
 * Each agent is issued an agent_key (Bearer token) used to authenticate
 * against the Print Hub agent API. The key is hashed before storage and
 * the raw value is shown only once at creation.
 */
class PrintAgent extends Model
{
    use BranchScopeable;

    protected $fillable = ['name', 'agent_key', 'ip_address', 'location', 'department', 'last_seen_at', 'is_active', 'printers', 'branch_id'];

    protected $hidden = ['agent_key'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_active'    => 'boolean',
        'printers'     => 'array',
    ];

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
     * Hash a raw agent key for storage.
     */
    public static function hashKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    /**
     * Look up a PrintAgent by its raw agent key.
     */
    public static function findByKey(string $rawKey): ?self
    {
        return static::where('agent_key', static::hashKey($rawKey))->first();
    }
}
