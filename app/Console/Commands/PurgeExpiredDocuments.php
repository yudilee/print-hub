<?php

namespace App\Console\Commands;

use App\Models\PrintDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print-hub:purge-expired-documents
                            {--dry-run : List documents that would be purged without actually deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete documents past their retain_until date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = PrintDocument::whereNotNull('retain_until')
            ->where('retain_until', '<', now())
            ->where('auto_delete', true);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired documents found to purge.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Original Name', 'Retain Until', 'Size'],
                $query->get(['id', 'original_name', 'retain_until', 'file_size'])->toArray()
            );
            $this->warn("{$count} document(s) would be purged (dry-run).");
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;
        $query->chunk(100, function ($documents) use ($bar, &$deleted) {
            foreach ($documents as $document) {
                try {
                    // Delete the physical file from storage
                    if ($document->storage_path && Storage::disk($document->disk ?? 'local')->exists($document->storage_path)) {
                        Storage::disk($document->disk ?? 'local')->delete($document->storage_path);
                    }

                    // Force-delete (hard delete) the record
                    $document->forceDelete();
                    $deleted++;
                } catch (\Exception $e) {
                    Log::error("Failed to purge document ID {$document->id}: {$e->getMessage()}");
                    $this->error("Failed to purge document ID {$document->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Successfully purged {$deleted} expired document(s).");

        return Command::SUCCESS;
    }
}
