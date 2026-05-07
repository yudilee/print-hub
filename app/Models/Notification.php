<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Notification
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $type
 * @property array|null $data
 * @property string|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static Builder|Notification unread()
 * @method static Builder|Notification byType(string $type)
 */
class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────

    /**
     * Scope to only unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to notifications of a specific type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Mark this notification as read.
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Check if the notification has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
