<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestScenario extends Model
{
    protected $fillable = [
        'print_template_id',
        'name',
        'description',
        'data',
        'is_default',
    ];

    protected $casts = [
        'data' => 'array',
        'is_default' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }
}
