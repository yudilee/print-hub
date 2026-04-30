<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = ActivityLog::with(['user', 'branch'])->latest('created_at');

        // Branch scoping
        if (!$user->isSuperAdmin()) {
            $branchIds = $user->getVisibleBranchIds();
            $query->where(function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds)
                  ->orWhereNull('branch_id');
            });
        }

        // Filters
        if ($request->filled('action')) {
            $query->where('action', 'like', $request->action . '%');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50);

        // Get filter options
        $actionTypes = ActivityLog::distinct()->pluck('action')->sort();
        $branches = $user->isSuperAdmin()
            ? Branch::with('company')->orderBy('name')->get()
            : Branch::where('company_id', $user->company_id)->orderBy('name')->get();

        return view('admin.activity-logs.index', compact('logs', 'actionTypes', 'branches'));
    }
}
