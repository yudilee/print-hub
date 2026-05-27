<?php

namespace App\Console\Commands;

use App\Models\PrinterPoolPrinter;
use Illuminate\Console\Command;

/**
 * Reset printer health status for printers that have been unhealthy
 * for more than 30 minutes. This allows automatic recovery without
 * manual intervention.
 */
class ResetPrinterHealth extends Command
{
    protected $signature   = 'print-hub:reset-printer-health';
    protected $description = 'Reset health status for printers unhealthy for > 30 minutes';

    public function handle(): int
    {
        $count = PrinterPoolPrinter::where('is_healthy', false)
            ->where('last_error_at', '<', now()->subMinutes(30))
            ->update([
                'is_healthy'     => true,
                'failure_count'  => 0,
                'last_error_at'  => null,
                'last_error_message' => null,
            ]);

        $this->info("Reset health for {$count} printer(s) that were unhealthy for > 30 minutes.");

        return self::SUCCESS;
    }
}
