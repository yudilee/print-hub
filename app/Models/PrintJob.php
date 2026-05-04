<?php

namespace App\Models;

use App\Traits\BranchScopeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PrintJob represents a single print request in the system.
 *
 * Jobs flow through states: pending → processing → success/failed.
 * They are created by client apps (via API) or admin test prints,
 * stored as PDF files, and dispatched to print agents for physical printing.
 */
class PrintJob extends Model
{
    use BranchScopeable;
    protected $fillable = [
        'job_id', 'print_agent_id', 'branch_id', 'document_id', 'printer_name', 'type', 'priority',
        'status', 'file_path', 'webhook_url', 'reference_id',
        'error', 'options', 'template_data', 'template_name',
        'agent_created_at', 'agent_completed_at',
        'scheduled_at', 'recurrence', 'recurrence_end_at', 'recurrence_count',
        'approval_status', 'approved_by', 'approved_at', 'rejected_reason', 'requires_approval',
        'pool_id',
    ];

    protected $casts = [
        'options'              => 'array',
        'template_data'        => 'array',
        'agent_created_at'     => 'datetime',
        'agent_completed_at'   => 'datetime',
        'priority'             => 'integer',
        'scheduled_at'         => 'datetime',
        'recurrence_end_at'    => 'datetime',
        'recurrence_count'     => 'integer',
        'approved_at'          => 'datetime',
        'requires_approval'    => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'print_agent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PrintDocument::class, 'document_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ───────────────────────────────────────────────

    /**
     * Scope to jobs that are scheduled and ready to be processed.
     */
    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->whereIn('status', ['pending', 'scheduled']);
    }

    /**
     * Scope to recurring jobs.
     */
    public function scopeRecurring($query)
    {
        return $query->whereNotNull('recurrence')
            ->where('recurrence', '!=', 'none');
    }

    /**
     * Scope to jobs pending approval.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }
}
