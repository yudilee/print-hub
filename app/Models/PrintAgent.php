<?php

namespace App\Models;

use App\Traits\BranchScopeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintAgent extends Model
{
    use BranchScopeable;
    protected $fillable = ['name', 'agent_key', 'ip_address', 'location', 'department', 'last_seen_at', 'is_active', 'printers', 'branch_id'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
        'printers' => 'array',
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
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 2;
    }
}
