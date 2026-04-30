<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['company_id', 'name', 'code', 'address', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(PrintAgent::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(PrintProfile::class);
    }

    public function templateDefaults(): HasMany
    {
        return $this->hasMany(BranchTemplateDefault::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function fullName(): string
    {
        return $this->company ? "{$this->company->code} / {$this->name}" : $this->name;
    }

    /**
     * Get the default queue (profile) for a given template in this branch.
     */
    public function getDefaultProfileForTemplate(int $templateId): ?PrintProfile
    {
        $default = $this->templateDefaults()
            ->where('print_template_id', $templateId)
            ->with('profile.agent')
            ->first();

        return $default?->profile;
    }
}
