<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'branch_id', 'action',
        'subject_type', 'subject_id',
        'properties', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Static Helper ────────────────────────────────────────

    /**
     * Record an activity log entry.
     */
    public static function record(string $action, $subject = null, array $properties = []): self
    {
        $user = auth()->user();

        return static::create([
            'user_id'      => $user?->id,
            'branch_id'    => $user?->branch_id,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->id ?? ($subject?->getKey() ?? null),
            'properties'   => !empty($properties) ? $properties : null,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
            'created_at'   => now(),
        ]);
    }
}
