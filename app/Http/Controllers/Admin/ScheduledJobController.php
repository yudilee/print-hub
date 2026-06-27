<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Controller for managing scheduled and recurring print jobs in the admin UI.
 *
 * Provides CRUD operations for creating scheduled print jobs that will
 * be processed by the ProcessScheduledJobs artisan command which runs
 * every minute.
 */
class ScheduledJobController extends Controller
{
    /**
     * Display a paginated list of scheduled/recurring print jobs.
     */
    public function index(Request $request)
    {
        $query = PrintJob::with('agent.branch')
            ->where(function ($q) {
                $q->whereNotNull('scheduled_at')
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('recurrence')
                           ->where('recurrence', '!=', 'none');
                  });
            });

        // ── Status filter ────────────────────────────────────
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ── Recurrence filter ────────────────────────────────
        if ($request->filled('recurrence')) {
            if ($request->recurrence === 'none') {
                $query->whereNull('recurrence')->orWhere('recurrence', 'none');
            } else {
                $query->where('recurrence', $request->recurrence);
            }
        }

        // ── Search ───────────────────────────────────────────
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_id', 'LIKE', "%{$search}%")
                  ->orWhere('template_name', 'LIKE', "%{$search}%")
                  ->orWhere('reference_id', 'LIKE', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $scheduledJobs = $query->orderBy('scheduled_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $profiles = PrintProfile::with('agent.branch')->orderBy('name')->get();

        return view('admin.scheduled-jobs.index', compact('scheduledJobs', 'profiles'));
    }

    /**
     * Show the create form for a new scheduled job.
     */
    public function create()
    {
        $profiles = PrintProfile::with('agent.branch')->orderBy('name')->get();

        return view('admin.scheduled-jobs.create', compact('profiles'));
    }

    /**
     * Store a new scheduled print job.
     *
     * Creates a PrintJob record from the selected PrintProfile with
     * the specified scheduled_at time and optional recurrence settings.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'print_profile_id'  => 'required|exists:print_profiles,id',
            'scheduled_at'      => 'required|date|after_or_equal:now',
            'recurrence'        => 'nullable|in:none,daily,weekly,monthly',
            'recurrence_end_at' => 'nullable|date|after:scheduled_at',
            'recurrence_count'  => 'nullable|integer|min:1|max:999',
            'template_name'     => 'nullable|string|max:255',
            'reference_id'      => 'nullable|string|max:255',
            'priority'          => 'nullable|integer|min:0|max:4',
        ]);

        $profile = PrintProfile::with('agent')->findOrFail($data['print_profile_id']);

        // Default recurrence to 'none' if not set
        $recurrence = $data['recurrence'] ?? 'none';

        $job = PrintJob::create([
            'job_id'            => (string) Str::uuid(),
            'print_agent_id'    => $profile->print_agent_id,
            'branch_id'         => $profile->branch_id,
            'printer_name'      => $profile->default_printer,
            'pool_id'           => $profile->pool_id,
            'type'              => 'template',
            'priority'          => $data['priority'] ?? 2,
            'status'            => 'scheduled',
            'template_name'     => $data['template_name'] ?? $profile->name,
            'reference_id'      => $data['reference_id'] ?? null,
            'options'           => [
                'copies'       => $profile->copies ?? 1,
                'duplex'       => $profile->duplex,
                'paper_size'   => $profile->paper_size,
                'orientation'  => $profile->orientation,
            ],
            'scheduled_at'      => $data['scheduled_at'],
            'recurrence'        => $recurrence !== 'none' ? $recurrence : null,
            'recurrence_end_at' => $data['recurrence_end_at'] ?? null,
            'recurrence_count'  => $data['recurrence_count'] ?? null,
        ]);

        return redirect()->route('admin.scheduled-jobs.index')
            ->with('success', "Scheduled job created. Job ID: {$job->job_id}");
    }

    /**
     * Cancel (delete) a scheduled job.
     */
    public function destroy(PrintJob $job)
    {
        if (!$job->scheduled_at && ($job->recurrence === null || $job->recurrence === 'none')) {
            return back()->withErrors(['error' => 'This job is not a scheduled job.']);
        }

        $job->delete();

        return redirect()->route('admin.scheduled-jobs.index')
            ->with('success', 'Scheduled job cancelled successfully.');
    }
}
