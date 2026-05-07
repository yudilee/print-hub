<?php

namespace App\Console\Commands;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use Illuminate\Console\Command;

/**
 * Reports records that still need manual key rotation after the bcrypt migration.
 *
 * Since sha256 is a one-way function, we cannot retroactively compute the
 * bcrypt hash for existing keys. This command identifies records where
 * `key_hash_bcrypt` is null but a legacy key exists, so an administrator
 * can rotate those keys manually.
 *
 * Run:  php artisan print-hub:migrate-key-hashing
 */
class MigrateKeyHashing extends Command
{
    protected $signature = 'print-hub:migrate-key-hashing
                            {--only-agents : Only check PrintAgent records}
                            {--only-clients : Only check ClientApp records}';

    protected $description = 'Report records that still need manual key rotation for bcrypt migration.';

    public function handle(): int
    {
        $checkAgents  = ! $this->option('only-clients');
        $checkClients = ! $this->option('only-agents');

        $agentNeedsRotation  = 0;
        $clientNeedsRotation = 0;

        // ── PrintAgents ──────────────────────────────────────

        if ($checkAgents) {
            $this->info('Checking PrintAgent records...');

            $agents = PrintAgent::whereNull('key_hash_bcrypt')
                ->whereNotNull('agent_key')
                ->get();

            foreach ($agents as $agent) {
                $this->warn(sprintf(
                    '  [AGENT #%d] "%s" — agent_key is set but key_hash_bcrypt is null. Key rotation required.',
                    $agent->id,
                    $agent->name
                ));
                $agentNeedsRotation++;
            }

            $this->info("  Total agents needing rotation: {$agentNeedsRotation}");
        }

        // ── ClientApps ───────────────────────────────────────

        if ($checkClients) {
            $this->info('Checking ClientApp records...');

            $clients = ClientApp::whereNull('key_hash_bcrypt')
                ->whereNotNull('api_key')
                ->get();

            foreach ($clients as $client) {
                $this->warn(sprintf(
                    '  [CLIENT #%d] "%s" — api_key is set but key_hash_bcrypt is null. Key rotation required.',
                    $client->id,
                    $client->name
                ));
                $clientNeedsRotation++;
            }

            $this->info("  Total clients needing rotation: {$clientNeedsRotation}");
        }

        // ── Summary ──────────────────────────────────────────

        $total = $agentNeedsRotation + $clientNeedsRotation;

        $this->newLine();
        $this->line('── Migration Summary ─────────────────────────────');
        $this->line("  PrintAgents needing rotation:  {$agentNeedsRotation}");
        $this->line("  ClientApps needing rotation:   {$clientNeedsRotation}");
        $this->line("  Total records requiring action: {$total}");

        if ($total === 0) {
            $this->info('  ✓ All records have been migrated to bcrypt.');
        } else {
            $this->warn("  ⚠  {$total} record(s) still need manual key rotation.");
            $this->line('  To rotate a key, use the admin panel or the regenerateKey method.');
        }

        return Command::SUCCESS;
    }
}
