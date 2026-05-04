<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

/**
 * Retries failed webhook deliveries that haven't exceeded their max attempts.
 *
 * This command should be scheduled to run periodically (e.g., every 5 minutes)
 * to pick up deliveries with `status = 'failed'` and `attempts < max_attempts`,
 * then attempt redelivery with exponential backoff.
 */
class RetryWebhooks extends Command
{
    protected $signature   = 'print-hub:retry-webhooks';
    protected $description = 'Retry failed webhook deliveries';

    public function handle(): int
    {
        $this->info('Retrying failed webhook deliveries...');

        $service = app(WebhookService::class);
        $count   = $service->retryFailed();

        $this->info("Retried {$count} webhook delivery(s).");

        return self::SUCCESS;
    }
}
