<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

/**
 * Cleans up old print job PDF files from storage to prevent disk bloat.
 *
 * Retention policy:
 *   - Completed (success/cancelled) jobs: files deleted after 7 days
 *   - Failed jobs: files deleted after 30 days
 *   - Pending/Processing jobs: never deleted automatically
 *
 * Run via scheduler weekly (see routes/console.php).
 */
class CleanupPrintJobs extends Command
{
    protected $signature   = 'print-hub:cleanup-jobs
                                {--success-days=7  : Days to retain completed job files}
                                {--failed-days=30  : Days to retain failed job files}
                                {--dry-run         : Preview what would be deleted without making changes}';
    protected $description = 'Delete old print job files from storage.';

    public function handle(): int
    {
        $successDays = (int) $this->option('success-days');
        $failedDays  = (int) $this->option('failed-days');
        $isDryRun    = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN — no files will be deleted.');
        }

        $deleted = 0;
        $freed   = 0;

        // Completed jobs
        $completedCutoff = Carbon::now()->subDays($successDays);
        $completedJobs   = PrintJob::whereIn('status', ['success', 'cancelled'])
            ->where('created_at', '<', $completedCutoff)
            ->whereNotNull('file_path')
            ->get();

        foreach ($completedJobs as $job) {
            [$d, $f] = $this->deleteJobFile($job, $isDryRun);
            $deleted += $d;
            $freed   += $f;
        }

        $this->info("Completed jobs (>{$successDays}d): {$deleted} file(s) cleaned.");

        // Failed jobs
        $failedBefore = $deleted;
        $failedCutoff = Carbon::now()->subDays($failedDays);
        $failedJobs   = PrintJob::where('status', 'failed')
            ->where('created_at', '<', $failedCutoff)
            ->whereNotNull('file_path')
            ->get();

        foreach ($failedJobs as $job) {
            [$d, $f] = $this->deleteJobFile($job, $isDryRun);
            $deleted += $d;
            $freed   += $f;
        }

        $this->info('Failed jobs (>' . $failedDays . 'd): ' . ($deleted - $failedBefore) . ' file(s) cleaned.');

        $freedMb = number_format($freed / 1024 / 1024, 2);
        $this->info("Total: {$deleted} file(s) removed, ~{$freedMb} MB freed.");

        return self::SUCCESS;
    }

    private function deleteJobFile(PrintJob $job, bool $isDryRun): array
    {
        if (! $job->file_path || ! Storage::exists($job->file_path)) {
            $job->update(['file_path' => null]);
            return [0, 0];
        }

        $size = Storage::size($job->file_path);
        $this->line("  🗑  {$job->file_path} ({$job->status}, created {$job->created_at->toDateString()})");

        if (! $isDryRun) {
            Storage::delete($job->file_path);
            $job->update(['file_path' => null]);
        }

        return [1, $size];
    }
}
