<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupPrintJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup {--days=30 : Number of days to keep jobs}';

    protected $description = 'Clean up old print jobs and their associated PDF files';

    public function handle()
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        $this->info("Cleaning up print jobs older than {$date->toDateTimeString()}...");

        $jobs = \App\Models\PrintJob::where('created_at', '<', $date)->get();
        $count = 0;

        foreach ($jobs as $job) {
            if ($job->file_path && \Illuminate\Support\Facades\Storage::exists($job->file_path)) {
                \Illuminate\Support\Facades\Storage::delete($job->file_path);
            }
            $job->delete();
            $count++;
        }

        $this->info("Successfully deleted {$count} old print jobs.");
    }
}
