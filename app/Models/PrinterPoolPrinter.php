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
    ];

    protected $casts = [
        'active'   => 'boolean',
        'priority' => 'integer',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(PrinterPool::class, 'pool_id');
    }
}
