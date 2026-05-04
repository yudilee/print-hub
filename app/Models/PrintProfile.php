<?php

namespace App\Models;

use App\Traits\BranchScopeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PrintProfile defines a named virtual print queue with complete printer configuration.
 *
 * Each profile specifies paper size, orientation, margins, copies, duplex, printer
 * tray, color mode, quality, and other advanced options. Profiles are pinned to a
 * specific print agent and branch for routing purposes.
 */
class PrintProfile extends Model
{
    use BranchScopeable;
    protected $fillable = [
        'name', 'description', 'print_agent_id', 'branch_id', 'paper_size', 'orientation',
        'copies', 'duplex', 'default_printer', 'extra_options',
        'is_custom', 'custom_width', 'custom_height',
        'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
        'tray_source', 'color_mode', 'print_quality', 'scaling_percentage',
        'media_type', 'collate', 'reverse_order',
    ];

    protected $casts = [
        'extra_options'       => 'array',
        'is_custom'           => 'boolean',
        'custom_width'        => 'float',
        'custom_height'       => 'float',
        'margin_top'          => 'float',
        'margin_bottom'       => 'float',
        'margin_left'         => 'float',
        'margin_right'        => 'float',
        'scaling_percentage'  => 'integer',
        'collate'             => 'boolean',
        'reverse_order'       => 'boolean',
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
