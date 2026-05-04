<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PrintApprovalRule defines conditions under which a print job
 * requires manual approval before being processed by an agent.
 */
class PrintApprovalRule extends Model
{
    protected $fillable = [
        'name',
        'rule_type',       // 'user', 'role', 'page_count', 'cost'
        'rule_value',      // the value to match
        'requires_approval',
        'approver_id',
        'active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'active'            => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
