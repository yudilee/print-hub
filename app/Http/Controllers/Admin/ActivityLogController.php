<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
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

        // ── Filters ──────────────────────────────────────────────

        // Action / event filter (existing)
        if ($request->filled('action')) {
            $query->where('action', 'like', $request->action . '%');
        }

        // Entity type filter (loggable_type)
        if ($request->filled('loggable_type')) {
            $query->where('subject_type', $request->loggable_type);
        }

        // Event filter — extract event from action (e.g. "job.created" → "created")
        if ($request->filled('event')) {
            $query->where('action', 'like', '%.' . $request->event);
        }

        // User filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Branch filter (existing)
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Date range filter (existing)
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50);

        // ── Filter options ───────────────────────────────────────

        // Action types (existing)
        $actionTypes = ActivityLog::distinct()->pluck('action')->sort();

        // Entity types — distinct loggable types that have been logged
        $entityTypes = ActivityLog::whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn ($class) => class_basename($class))
            ->unique()
            ->sort()
            ->values();

        // Events — extract unique event names from action column
        $events = ActivityLog::distinct()
            ->pluck('action')
            ->map(fn ($action) => last(explode('.', $action)))
            ->unique()
            ->sort()
            ->values();

        // Users who have performed actions
        $users = User::whereIn('id', ActivityLog::whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('name')
            ->get();

        $branches = $user->isSuperAdmin()
            ? Branch::with('company')->orderBy('name')->get()
            : Branch::where('company_id', $user->company_id)->orderBy('name')->get();

        return view('admin.activity-logs.index', compact(
            'logs', 'actionTypes', 'entityTypes', 'events', 'users', 'branches'
        ));
    }

    /**
     * Export filtered activity logs as CSV.
     */
    public function exportCsv(Request $request)
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

        // Apply same filters as index()
        if ($request->filled('action')) {
            $query->where('action', 'like', $request->action . '%');
        }
        if ($request->filled('loggable_type')) {
            $query->where('subject_type', $request->loggable_type);
        }
        if ($request->filled('event')) {
            $query->where('action', 'like', '%.' . $request->event);
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

        $logs = $query->get();

        $filename = 'activity-logs-export-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'Timestamp', 'User', 'Branch', 'Action', 'Event',
                'Subject Type', 'Subject ID', 'IP Address', 'Properties',
            ]);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->branch?->name ?? '',
                    $log->action,
                    last(explode('.', $log->action)),
                    $log->subject_type ? class_basename($log->subject_type) : '',
                    $log->subject_id ?? '',
                    $log->ip_address ?? '',
                    $log->properties ? json_encode($log->properties) : '',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
