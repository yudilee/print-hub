<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PrintCost represents the cost incurred for a single print job.
 *
 * Costs are calculated based on the branch's cost-per-page settings
 * and whether the job was printed in color or black-and-white.
 */
class PrintCost extends Model
{
    protected $fillable = [
        'print_job_id',
        'branch_id',
        'print_agent_id',
        'pages_printed',
        'is_color',
        'cost_per_page',
        'total_cost',
        'currency',
    ];

    protected $casts = [
        'pages_printed' => 'integer',
        'is_color'      => 'boolean',
        'cost_per_page' => 'decimal:4',
        'total_cost'    => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printAgent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class);
    }
}
