<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Processes scheduled and recurring print jobs.
 *
 * Runs periodically (every minute recommended) to pick up jobs whose
 * `scheduled_at` time has passed and queue them for agent processing.
 * For recurring jobs, a new PrintJob copy is created with the next
 * scheduled time.
 */
class ProcessScheduledJobs extends Command
{
    protected $signature   = 'print-hub:process-scheduled';
    protected $description = 'Process scheduled and recurring print jobs';

    public function handle(): int
    {
        $processed = 0;
        $created   = 0;

        $jobs = PrintJob::where('scheduled_at', '<=', now())
            ->whereIn('status', ['pending', 'scheduled'])
            ->get();

        foreach ($jobs as $job) {
            // Mark as queued so the agent can pick it up
            $job->update(['status' => 'queued']);
            $processed++;

            $this->line("  ✅ Job {$job->job_id} queued (scheduled: {$job->scheduled_at})");

            // If the job is recurring, create the next occurrence
            if ($job->recurrence && $job->recurrence !== 'none') {
                $nextJob = $this->createNextRecurrence($job);
                if ($nextJob) {
                    $created++;
                    $this->line("  🔄 Next recurrence created: {$nextJob->job_id} (scheduled: {$nextJob->scheduled_at})");
                }
            }
        }

        $this->info("Processed {$processed} scheduled job(s), created {$created} recurrence(s).");

        return self::SUCCESS;
    }

    /**
     * Create the next occurrence of a recurring job.
     * Returns the new PrintJob or null if recurrence limit reached.
     */
    private function createNextRecurrence(PrintJob $job): ?PrintJob
    {
        // Check recurrence_count limit (0 = unlimited)
        if ($job->recurrence_count !== null && $job->recurrence_count > 0) {
            $occurrences = PrintJob::where('job_id', 'like', $job->job_id . '-%')->count();
            if ($occurrences >= $job->recurrence_count) {
                $this->line("  ⏹  Recurrence limit ({$job->recurrence_count}) reached for {$job->job_id}");
                return null;
            }
        }

        // Calculate next scheduled_at
        $nextScheduledAt = $this->calculateNextDate($job);

        if ($nextScheduledAt === null) {
            return null;
        }

        // Check recurrence_end_at
        if ($job->recurrence_end_at && $nextScheduledAt->greaterThan($job->recurrence_end_at)) {
            $this->line("  ⏹  Recurrence end date reached for {$job->job_id}");
            return null;
        }

        // Create the new job as a copy
        $newJobId = $job->job_id . '-' . $nextScheduledAt->format('YmdHi');

        return PrintJob::create([
            'job_id'             => $newJobId,
            'print_agent_id'     => $job->print_agent_id,
            'branch_id'          => $job->branch_id,
            'document_id'        => $job->document_id,
            'printer_name'       => $job->printer_name,
            'type'               => $job->type,
            'priority'           => $job->priority,
            'status'             => 'scheduled',
            'file_path'          => $job->file_path,
            'webhook_url'        => $job->webhook_url,
            'reference_id'       => $job->reference_id,
            'options'            => $job->options,
            'template_data'      => $job->template_data,
            'template_name'      => $job->template_name,
            'scheduled_at'       => $nextScheduledAt,
            'recurrence'         => $job->recurrence,
            'recurrence_end_at'  => $job->recurrence_end_at,
            'recurrence_count'   => $job->recurrence_count,
        ]);
    }

    /**
     * Compute the next scheduled date based on recurrence type.
     */
    private function calculateNextDate(PrintJob $job): ?Carbon
    {
        $current = $job->scheduled_at ? Carbon::parse($job->scheduled_at) : now();

        return match ($job->recurrence) {
            'daily'   => $current->copy()->addDay(),
            'weekly'  => $current->copy()->addWeek(),
            'monthly' => $current->copy()->addMonth(),
            default   => null,
        };
    }
}
