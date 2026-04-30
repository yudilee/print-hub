<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchTemplateDefault extends Model
{
    protected $fillable = ['branch_id', 'print_template_id', 'print_profile_id'];

    // ── Relationships ────────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PrintProfile::class, 'print_profile_id');
    }
}
