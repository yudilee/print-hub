<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrinterPool extends Model
{
    protected $fillable = [
        'name',
        'description',
        'strategy',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function printers(): HasMany
    {
        return $this->hasMany(PrinterPoolPrinter::class, 'pool_id');
    }

    /**
     * Get active printers in the pool, ordered by priority.
     */
    public function activePrinters(): HasMany
    {
        return $this->hasMany(PrinterPoolPrinter::class, 'pool_id')
            ->where('active', true)
            ->orderBy('priority');
    }
}
