<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintAgent extends Model
{
    protected $fillable = ['name', 'agent_key', 'ip_address', 'last_seen_at', 'is_active', 'printers'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
        'printers' => 'array',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 2;
    }
}
