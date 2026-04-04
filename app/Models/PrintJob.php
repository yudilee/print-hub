<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    protected $fillable = [
        'job_id', 'print_agent_id', 'printer_name', 'type',
        'status', 'file_path', 'webhook_url', 'reference_id',
        'error', 'options', 'agent_created_at', 'agent_completed_at',
    ];

    protected $casts = [
        'options' => 'array',
        'agent_created_at' => 'datetime',
        'agent_completed_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'print_agent_id');
    }
}
