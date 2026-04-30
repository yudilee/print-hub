<?php

namespace App\Models;

use App\Traits\BranchScopeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintProfile extends Model
{
    use BranchScopeable;
    protected $fillable = [
        'name', 'description', 'print_agent_id', 'branch_id', 'paper_size', 'orientation',
        'copies', 'duplex', 'default_printer', 'extra_options',
        'is_custom', 'custom_width', 'custom_height',
        'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
    ];

    protected $casts = [
        'extra_options' => 'array',
        'is_custom' => 'boolean',
        'custom_width' => 'float',
        'custom_height' => 'float',
        'margin_top' => 'float',
        'margin_bottom' => 'float',
        'margin_left' => 'float',
        'margin_right' => 'float',
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
