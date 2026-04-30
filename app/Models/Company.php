<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Company extends Model
{
    protected $fillable = ['name', 'code', 'short_name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Branch::class, 'company_id', 'branch_id');
    }

    public function agents(): HasManyThrough
    {
        return $this->hasManyThrough(PrintAgent::class, Branch::class, 'company_id', 'branch_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function displayName(): string
    {
        if ($this->short_name) {
            return "{$this->name} ({$this->short_name})";
        }
        return $this->name;
    }
}
