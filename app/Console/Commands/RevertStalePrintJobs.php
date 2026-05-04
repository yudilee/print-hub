<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Reverts print jobs that have been stuck in "processing" state for too long.
 *
 * This guards against agents that fetch a job from the queue and then crash
 * before reporting completion. Without this, such jobs would remain in
 * "processing" indefinitely and never be retried.
 *
 * Run via scheduler every minute (see routes/console.php).
 */
class RevertStalePrintJobs extends Command
{
    protected $signature   = 'print-hub:revert-stale-jobs {--minutes=5 : Jobs older than this many minutes will be reverted}';
    protected $description = 'Revert print jobs stuck in "processing" state back to "pending" for retry.';

    public function handle(): int
    {
        $minutes  = (int) $this->option('minutes');
        $cutoff   = Carbon::now()->subMinutes($minutes);

        $staleJobs = PrintJob::where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($staleJobs->isEmpty()) {
            $this->line("No stale jobs found.");
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($staleJobs as $job) {
            $job->update([
                'status' => 'pending',
                'error'  => "Auto-reverted: job was stuck in 'processing' for over {$minutes} minutes.",
            ]);
            $count++;
            $this->line("  ↩ Reverted job {$job->job_id} (last updated {$job->updated_at->diffForHumans()})");
        }

        $this->info("Reverted {$count} stale job(s) back to 'pending'.");
        return self::SUCCESS;
    }
}
