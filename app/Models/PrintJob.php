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
        'job_id', 'print_agent_id', 'branch_id', 'printer_name', 'type', 'priority',
        'status', 'file_path', 'webhook_url', 'reference_id',
        'error', 'options', 'template_data', 'template_name',
        'agent_created_at', 'agent_completed_at',
    ];

    protected $casts = [
        'options'              => 'array',
        'template_data'        => 'array',
        'agent_created_at'     => 'datetime',
        'agent_completed_at'   => 'datetime',
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
}
