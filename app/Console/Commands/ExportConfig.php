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
use Illuminate\Support\Facades\Storage;

/**
 * Exports Print Hub configuration (agents, profiles, templates, fonts, pools,
 * settings, releases) as a portable JSON file for backup or migration.
 *
 * Usage:
 *   php artisan print-hub:export-config
 *   php artisan print-hub:export-config --output=custom-backup.json
 */
class ExportConfig extends Command
{
    protected $signature = 'print-hub:export-config
                            {--output= : Output filename relative to storage/app/backup/ (default: auto-generated)}';

    protected $description = 'Export Print Hub configuration to a JSON backup file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Exporting Print Hub configuration...');

        $backupDir = storage_path('app/backup');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = $this->option('output') ?: 'print-hub-config-' . now()->format('Y-m-d_His') . '.json';
        $filePath = $backupDir . '/' . $filename;

        $export = [
            'exported_at'  => now()->toIso8601String(),
            'version'      => '1.0',
            'application'  => config('app.name', 'Print Hub'),
            'data'         => [
                'agents'         => $this->exportAgents(),
                'profiles'       => $this->exportProfiles(),
                'templates'      => $this->exportTemplates(),
                'fonts'          => $this->exportFonts(),
                'printer_pools'  => $this->exportPrinterPools(),
                'printer_configs' => $this->exportPrinterConfigs(),
                'settings'       => $this->exportSettings(),
                'releases'       => $this->exportReleases(),
            ],
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($filePath, $json) === false) {
            $this->error("Failed to write backup file: {$filePath}");
            return self::FAILURE;
        }

        $this->info("✅ Configuration exported successfully.");
        $this->line("   File: {$filePath}");
        $this->line("   Size: " . $this->formatBytes(strlen($json)));

        return self::SUCCESS;
    }

    /**
     * Export all print agents.
     */
    private function exportAgents(): array
    {
        return PrintAgent::all()->map(function ($agent) {
            return $agent->toArray();
        })->toArray();
    }

    /**
     * Export all print profiles.
     */
    private function exportProfiles(): array
    {
        return PrintProfile::all()->map(function ($profile) {
            return $profile->toArray();
        })->toArray();
    }

    /**
     * Export all print templates (without the actual background image binary data).
     */
    private function exportTemplates(): array
    {
        return PrintTemplate::all()->map(function ($template) {
            $data = $template->toArray();
            // Exclude large binary data that can be re-uploaded
            unset($data['background_image']);
            return $data;
        })->toArray();
    }

    /**
     * Export all fonts.
     */
    private function exportFonts(): array
    {
        return PrintFont::all()->map(function ($font) {
            $data = $font->toArray();
            // Exclude file binary data, keep metadata only
            unset($data['file_data']);
            return $data;
        })->toArray();
    }

    /**
     * Export all printer pools.
     */
    private function exportPrinterPools(): array
    {
        return PrinterPool::with('printers')->get()->map(function ($pool) {
            $data = $pool->toArray();
            $data['printer_ids'] = $pool->printers->pluck('id')->toArray();
            return $data;
        })->toArray();
    }

    /**
     * Export all printer configurations.
     */
    private function exportPrinterConfigs(): array
    {
        return PrinterConfig::all()->map(function ($config) {
            return $config->toArray();
        })->toArray();
    }

    /**
     * Export all system settings.
     */
    private function exportSettings(): array
    {
        return Setting::all()->map(function ($setting) {
            return [
                'key'   => $setting->key,
                'value' => $setting->value,
                'type'  => $setting->type,
            ];
        })->toArray();
    }

    /**
     * Export all agent releases.
     */
    private function exportReleases(): array
    {
        return AgentRelease::all()->map(function ($release) {
            return $release->toArray();
        })->toArray();
    }

    /**
     * Format bytes into human-readable string.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}
