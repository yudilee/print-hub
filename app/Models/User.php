<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'auth_source',
        'branch_id',
        'company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Role Helpers ─────────────────────────────────────────

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super-admin';
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === 'company-admin';
    }

    public function isBranchAdmin(): bool
    {
        return $this->role === 'branch-admin';
    }

    /**
     * Check if user has company-wide visibility (super-admin, company-admin, or branch-admin).
     */
    public function hasCompanyScope(): bool
    {
        return in_array($this->role, ['super-admin', 'company-admin', 'branch-admin']);
    }

    /**
     * Get all branch IDs visible to this user.
     */
    public function getVisibleBranchIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Branch::pluck('id')->all();
        }

        if ($this->hasCompanyScope() && $this->company_id) {
            return Branch::where('company_id', $this->company_id)->pluck('id')->all();
        }

        return $this->branch_id ? [$this->branch_id] : [];
    }
}
