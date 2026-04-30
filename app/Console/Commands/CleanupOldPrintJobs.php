<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldPrintJobs extends Command
{
    protected $signature = 'print-hub:cleanup {--days=30 : Number of days to keep completed job files}';
    protected $description = 'Delete stored files for completed print jobs older than N days (keeps DB records for audit)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $this->info("Cleaning up completed job files older than {$days} days ({$cutoff->toDateString()})...");

        $jobs = PrintJob::whereIn('status', ['success', 'failed'])
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('file_path')
            ->get();

        $cleaned = 0;
        $freedBytes = 0;

        foreach ($jobs as $job) {
            if (Storage::exists($job->file_path)) {
                $freedBytes += Storage::size($job->file_path);
                Storage::delete($job->file_path);
                $cleaned++;
            }
            // Clear the file_path to indicate file has been purged
            $job->update(['file_path' => null]);
        }

        $freedMB = round($freedBytes / 1024 / 1024, 2);
        $this->info("✓ Cleaned {$cleaned} job files. Freed {$freedMB} MB.");
        $this->info("  (DB records preserved for audit trail)");

        return Command::SUCCESS;
    }
}
