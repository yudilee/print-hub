<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PrinterConfig stores per-printer configuration overrides for print agents.
 *
 * Each record ties a specific printer on a specific agent to a set of
 * configuration options (copies, duplex, paper size, tray, etc.). These
 * configs are managed from the Print Hub admin UI and synced to agents.
 */
class PrinterConfig extends Model
{
    protected $fillable = [
        'print_agent_id',
        'printer_name',
        'config',
        'is_active',
    ];

    protected $casts = [
        'config'    => 'array',
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'print_agent_id');
    }
}
