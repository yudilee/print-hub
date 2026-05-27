<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterPoolPrinter extends Model
{
    protected $fillable = [
        'pool_id',
        'printer_name',
        'priority',
        'active',
        'last_healthy_at',
        'failure_count',
        'is_healthy',
        'last_error_at',
        'last_error_message',
    ];

    protected $casts = [
        'active'          => 'boolean',
        'priority'        => 'integer',
        'failure_count'   => 'integer',
        'is_healthy'      => 'boolean',
        'last_healthy_at' => 'datetime',
        'last_error_at'   => 'datetime',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(PrinterPool::class, 'pool_id');
    }
}
