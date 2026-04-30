<?php

namespace App\Traits;

use App\Models\Branch;

/**
 * Provides branch-scoped query methods for models with a branch_id column.
 *
 * Apply to: PrintAgent, PrintProfile, PrintJob
 */
trait BranchScopeable
{
    /**
     * Scope query to the authenticated user's visible branches.
     * - super-admin: sees all
     * - company-admin / branch-admin: sees all branches in their company
     * - branch-operator / viewer: sees only their branch
     */
    public function scopeForUserBranch($query, ?\App\Models\User $user = null)
    {
        $user = $user ?? auth()->user();

        if (!$user) return $query->whereRaw('1 = 0'); // no user = no results

        if ($user->isSuperAdmin()) return $query;

        $branchIds = $user->getVisibleBranchIds();

        return $query->whereIn($this->getTable() . '.branch_id', $branchIds);
    }
}
