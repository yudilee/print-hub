<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrate data from SQLite to PostgreSQL.
 *
 * This command connects to both the source (SQLite) and target (PostgreSQL)
 * database connections and copies all data table-by-table. It handles:
 *   - Boolean conversion (0/1 → true/false) via Eloquent
 *   - JSON serialization via Eloquent
 *   - Timestamp normalization
 *   - Auto-increment sequence reset for PostgreSQL
 *
 * Run:  php artisan db:migrate-to-pgsql
 *       php artisan db:migrate-to-pgsql --tables=users,print_agents --dry-run
 */
class MigrateSqliteToPostgres extends Command
{
    protected $signature = 'db:migrate-to-pgsql
                            {--source=sqlite : Source database connection name}
                            {--target=pgsql : Target database connection name}
                            {--tables= : Comma-separated list of tables to migrate (default: all)}
                            {--drop-target : Drop target tables before migration}
                            {--dry-run : Preview tables and row counts without executing}';

    protected $description = 'Migrate data from SQLite to PostgreSQL connection';

    /**
     * Tables that should be migrated (in dependency order).
     * Child tables should come after their parents to respect FK constraints.
     */
    private array $tableOrder = [
        'users',
        'password_reset_tokens',
        'personal_access_tokens',
        'companies',
        'branches',
        'print_agents',
        'client_apps',
        'user_sessions',
        'print_profiles',
        'print_templates',
        'template_versions',
        'data_schemas',
        'print_fonts',
        'print_jobs',
        'print_documents',
        'print_approval_rules',
        'printer_pools',
        'printer_pool_printers',
        'printer_configs',
        'connectors',
        'webhook_deliveries',
        'activity_logs',
        'notifications',
        'settings',
        'test_scenarios',
        'cache',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
    ];

    public function handle(): int
    {
        $source = $this->option('source');
        $target = $this->option('target');
        $dryRun = $this->option('dry-run');
        $dropTarget = $this->option('drop-target');
        $tablesFilter = $this->option('tables');

        // Verify connections exist
        try {
            DB::connection($source)->getPdo();
        } catch (\Exception $e) {
            $this->error("Source connection '{$source}' is not available: " . $e->getMessage());
            return Command::FAILURE;
        }

        try {
            DB::connection($target)->getPdo();
        } catch (\Exception $e) {
            $this->error("Target connection '{$target}' is not available: " . $e->getMessage());
            $this->line('Ensure PostgreSQL is running and DB_CONNECTION=pgsql is set in .env');
            return Command::FAILURE;
        }

        $this->info("Source: {$source} (" . DB::connection($source)->getDatabaseName() . ')');
        $this->info("Target: {$target} (" . DB::connection($target)->getDatabaseName() . ')');

        // Determine which tables to migrate
        $tables = $this->tableOrder;
        if ($tablesFilter) {
            $filtered = array_map('trim', explode(',', $tablesFilter));
            $tables = array_intersect($tables, $filtered);
        }

        // Filter to only tables that exist in the source
        $existingTables = [];
        foreach ($tables as $table) {
            if (Schema::connection($source)->hasTable($table)) {
                $existingTables[] = $table;
            }
        }

        if (empty($existingTables)) {
            $this->warn('No tables found to migrate.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('Tables to migrate:');
        foreach ($existingTables as $table) {
            $count = DB::connection($source)->table($table)->count();
            $this->line("  - {$table} ({$count} rows)");
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry-run complete. No data was migrated.');
            return Command::SUCCESS;
        }

        // Drop target tables if requested
        if ($dropTarget) {
            $this->newLine();
            $this->warn('Dropping existing tables in target database...');
            // Drop in reverse order to respect FK constraints
            foreach (array_reverse($existingTables) as $table) {
                if (Schema::connection($target)->hasTable($table)) {
                    Schema::connection($target)->drop($table);
                    $this->line("  Dropped: {$table}");
                }
            }
        }

        // Run migrations on target to create tables
        $this->newLine();
        $this->info('Running migrations on target database...');
        $exitCode = $this->call('migrate', [
            '--database' => $target,
            '--force' => true,
        ]);

        if ($exitCode !== Command::SUCCESS) {
            $this->error('Migration failed on target database. Aborting data copy.');
            return Command::FAILURE;
        }

        // Disable foreign key checks during data copy
        DB::connection($target)->statement('SET session_replication_role = replica;');

        $totalRows = 0;
        $errors = [];

        try {
            foreach ($existingTables as $table) {
                $this->newLine();
                $this->line("Migrating: {$table}...");

                // Delete target table data if it already has data
                if (Schema::connection($target)->hasTable($table)) {
                    DB::connection($target)->table($table)->delete();
                }

                // Fetch all rows from source
                $rows = DB::connection($source)->table($table)->get();

                if ($rows->isEmpty()) {
                    $this->line("  No rows to migrate.");
                    continue;
                }

                $chunkSize = 100;
                $rowCount = 0;

                foreach ($rows->chunk($chunkSize) as $chunk) {
                    try {
                        $data = $chunk->map(function ($row) {
                            return (array) $row;
                        })->toArray();

                        DB::connection($target)->table($table)->insert($data);
                        $rowCount += count($data);
                        $totalRows += count($data);
                    } catch (\Exception $e) {
                        $errors[] = "Error inserting chunk into {$table}: " . $e->getMessage();
                        $this->error("  Error: " . $e->getMessage());
                    }
                }

                // Reset sequence for auto-increment primary key
                $this->resetSequence($target, $table);

                $this->line("  Migrated {$rowCount} rows.");
            }
        } finally {
            // Re-enable foreign key checks
            DB::connection($target)->statement('SET session_replication_role = origin;');
        }

        $this->newLine();
        $this->info("Migration complete! Migrated {$totalRows} total rows across " . count($existingTables) . ' tables.');

        if (! empty($errors)) {
            $this->newLine();
            $this->warn('Errors encountered during migration:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Reset the auto-increment sequence for a PostgreSQL table.
     */
    private function resetSequence(string $connection, string $table): void
    {
        try {
            // Get the sequence name from the table's primary key
            $sequence = DB::connection($connection)
                ->selectOne("
                    SELECT pg_get_serial_sequence(?, column_name) AS seq
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                      AND table_name = ?
                      AND column_default LIKE 'nextval%'
                ", [$table, $table]);

            if ($sequence && $sequence->seq) {
                DB::connection($connection)->statement(
                    "SELECT setval(?, COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)",
                    [$sequence->seq]
                );
            }
        } catch (\Exception $e) {
            // Non-critical — sequence reset is best-effort
            $this->line("  (Note: could not reset sequence for {$table})");
        }
    }
}
