<?php

namespace App\Console\Commands;

use App\Models\PrintAgent;
use App\Models\PrintFont;
use App\Models\PrintProfile;
use App\Models\PrinterConfig;
use App\Models\PrinterPool;
use App\Models\PrintTemplate;
use App\Models\Setting;
use App\Models\AgentRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports Print Hub configuration from a previously exported JSON file.
 *
 * This command reads a JSON backup file and restores agents, profiles,
 * templates, fonts, pools, settings, and releases. Existing records
 * are matched by ID or key and updated; new records are created.
 *
 * Usage:
 *   php artisan print-hub:import-config storage/app/backup/print-hub-config-2026-05-27.json
 *   php artisan print-hub:import-config --file=custom-backup.json
 */
class ImportConfig extends Command
{
    protected $signature = 'print-hub:import-config
                            {file? : Path to the JSON backup file (relative to storage/app/backup/ or absolute)}
                            {--file= : Alternative way to specify the backup file path}';

    protected $description = 'Import Print Hub configuration from a JSON backup file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file') ?: $this->option('file');

        if (!$filePath) {
            $this->error('Please specify the backup file path.');
            $this->line('Usage: php artisan print-hub:import-config {file}');
            $this->line('Example: php artisan print-hub:import-config storage/app/backup/print-hub-config-2026-05-27.json');
            return self::FAILURE;
        }

        // Resolve the file path
        if (!file_exists($filePath)) {
            // Try relative to storage/app/backup/
            $altPath = storage_path('app/backup/' . ltrim($filePath, '/'));
            if (file_exists($altPath)) {
                $filePath = $altPath;
            } else {
                // Try relative to storage/
                $altPath2 = storage_path(ltrim($filePath, '/'));
                if (file_exists($altPath2)) {
                    $filePath = $altPath2;
                }
            }
        }

        if (!file_exists($filePath)) {
            $this->error("Backup file not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Reading backup file: {$filePath}");

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON file: ' . json_last_error_msg());
            return self::FAILURE;
        }

        if (!isset($data['data'])) {
            $this->error('Invalid backup format: missing "data" key.');
            return self::FAILURE;
        }

        $this->info('Importing configuration...');
        $this->line("   Exported at: {$data['exported_at']}");
        $this->line("   Version: {$data['version']}");

        $counts = [
            'agents'          => 0,
            'profiles'        => 0,
            'templates'       => 0,
            'fonts'           => 0,
            'printer_pools'   => 0,
            'printer_configs' => 0,
            'settings'        => 0,
            'releases'        => 0,
        ];

        DB::beginTransaction();

        try {
            // Import in dependency order
            if (!empty($data['data']['settings'])) {
                $counts['settings'] = $this->importSettings($data['data']['settings']);
            }

            if (!empty($data['data']['agents'])) {
                $counts['agents'] = $this->importAgents($data['data']['agents']);
            }

            if (!empty($data['data']['templates'])) {
                $counts['templates'] = $this->importTemplates($data['data']['templates']);
            }

            if (!empty($data['data']['fonts'])) {
                $counts['fonts'] = $this->importFonts($data['data']['fonts']);
            }

            if (!empty($data['data']['profiles'])) {
                $counts['profiles'] = $this->importProfiles($data['data']['profiles']);
            }

            if (!empty($data['data']['printer_configs'])) {
                $counts['printer_configs'] = $this->importPrinterConfigs($data['data']['printer_configs']);
            }

            if (!empty($data['data']['printer_pools'])) {
                $counts['printer_pools'] = $this->importPrinterPools($data['data']['printer_pools']);
            }

            if (!empty($data['data']['releases'])) {
                $counts['releases'] = $this->importReleases($data['data']['releases']);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Import failed: ' . $e->getMessage());
            $this->line('All changes have been rolled back.');
            return self::FAILURE;
        }

        $this->info('✅ Configuration imported successfully!');
        $this->table(
            ['Entity', 'Imported'],
            array_map(fn ($key, $count) => [ucfirst(str_replace('_', ' ', $key)), $count], array_keys($counts), $counts)
        );

        return self::SUCCESS;
    }

    private function importSettings(array $settings): int
    {
        $count = 0;
        foreach ($settings as $item) {
            Setting::updateOrCreate(
                ['key' => $item['key']],
                ['value' => $item['value'], 'type' => $item['type'] ?? 'string']
            );
            $count++;
        }
        $this->line("   Imported {$count} settings");
        return $count;
    }

    private function importAgents(array $agents): int
    {
        $count = 0;
        foreach ($agents as $item) {
            PrintAgent::updateOrCreate(
                ['id' => $item['id']],
                collect($item)->except(['id', 'created_at', 'updated_at'])->toArray()
            );
            $count++;
        }
        $this->line("   Imported {$count} agents");
        return $count;
    }

    private function importTemplates(array $templates): int
    {
        $count = 0;
        foreach ($templates as $item) {
            PrintTemplate::updateOrCreate(
                ['id' => $item['id']],
                collect($item)->except(['id', 'created_at', 'updated_at', 'background_image'])->toArray()
            );
            $count++;
        }
        $this->line("   Imported {$count} templates");
        return $count;
    }

    private function importFonts(array $fonts): int
    {
        $count = 0;
        foreach ($fonts as $item) {
            PrintFont::updateOrCreate(
                ['id' => $item['id']],
                collect($item)->except(['id', 'created_at', 'updated_at', 'file_data'])->toArray()
            );
            $count++;
        }
        $this->line("   Imported {$count} fonts");
        return $count;
    }

    private function importProfiles(array $profiles): int
    {
        $count = 0;
        foreach ($profiles as $item) {
            PrintProfile::updateOrCreate(
                ['id' => $item['id']],
                collect($item)->except(['id', 'created_at', 'updated_at'])->toArray()
            );
            $count++;
        }
        $this->line("   Imported {$count} profiles");
        return $count;
    }

    private function importPrinterConfigs(array $configs): int
    {
        $count = 0;
        foreach ($configs as $item) {
            PrinterConfig::updateOrCreate(
                ['id' => $item['id']],
                collect($item)->except(['id', 'created_at', 'updated_at'])->toArray()
            );
            $count++;
        }
        $this->line("   Imported {$count} printer configs");
        return $count;
    }

    private function importPrinterPools(array $pools): int
    {
        $count = 0;
        foreach ($pools as $item) {
            $printerIds = $item['printer_ids'] ?? [];

            $pool = PrinterPool::updateOrCreate(
                ['id' => $item['id']],
                collect($item)->except(['id', 'created_at', 'updated_at', 'printer_ids'])->toArray()
            );

            // Sync printer associations
            if (!empty($printerIds)) {
                $pool->printers()->sync($printerIds);
            }

            $count++;
        }
        $this->line("   Imported {$count} printer pools");
        return $count;
    }

    private function importReleases(array $releases): int
    {
        $count = 0;
        foreach ($releases as $item) {
            AgentRelease::updateOrCreate(
                ['id' => $item['id']],
                collect($item)->except(['id', 'created_at', 'updated_at'])->toArray()
            );
            $count++;
        }
        $this->line("   Imported {$count} releases");
        return $count;
    }
}
