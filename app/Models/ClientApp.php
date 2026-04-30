<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientApp extends Model
{
    protected $fillable = ['name', 'api_key', 'is_active', 'last_used_at', 'allowed_origins'];

    protected $casts = [
        'is_active'       => 'boolean',
        'last_used_at'    => 'datetime',
        'allowed_origins' => 'array',
    ];
}
