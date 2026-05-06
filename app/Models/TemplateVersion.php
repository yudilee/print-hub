<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    protected $fillable = [
        'print_template_id',
        'version_number',
        'elements',
        'styles',
        'properties',
        'label',
        'changelog',
        'created_by',
    ];

    protected $casts = [
        'elements'       => 'array',
        'styles'         => 'array',
        'properties'     => 'array',
        'version_number' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
