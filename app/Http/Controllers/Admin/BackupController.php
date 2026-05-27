<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Admin UI controller for backup and restore operations.
 *
 * Provides a web interface for exporting and importing Print Hub
 * configuration via the artisan commands.
 */
class BackupController extends Controller
{
    /**
     * Display the backup/restore page with existing backup files.
     */
    public function index()
    {
        $backupDir = storage_path('app/backup');

        $backupFiles = [];
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/print-hub-config-*.json');
            rsort($files); // Most recent first

            foreach ($files as $file) {
                $backupFiles[] = [
                    'name'     => basename($file),
                    'size'     => $this->formatBytes(filesize($file)),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'path'     => $file,
                ];
            }
        }

        return view('admin.backup.index', compact('backupFiles'));
    }

    /**
     * Export configuration to a JSON file.
     */
    public function export()
    {
        $exitCode = Artisan::call('print-hub:export-config');

        if ($exitCode !== 0) {
            return redirect()->route('admin.backup.index')
                ->withErrors(['export' => 'Export failed: ' . Artisan::output()]);
        }

        // Extract the filename from the output
        $output = Artisan::output();
        preg_match('/File: (.+)/', $output, $matches);
        $filePath = $matches[1] ?? null;

        return redirect()->route('admin.backup.index')
            ->with('success', '✅ Configuration exported successfully.' . ($filePath ? " File: " . basename($filePath) : ''));
    }

    /**
     * Import configuration from an uploaded JSON file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:json|max:10240', // 10MB max
        ]);

        $uploadedFile = $request->file('backup_file');
        $backupDir = storage_path('app/backup');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Store the uploaded file
        $storedPath = $uploadedFile->storeAs('backup', $uploadedFile->getClientOriginalName());

        $fullPath = storage_path('app/' . $storedPath);

        $exitCode = Artisan::call('print-hub:import-config', ['file' => $fullPath]);

        if ($exitCode !== 0) {
            return redirect()->route('admin.backup.index')
                ->withErrors(['import' => 'Import failed: ' . Artisan::output()]);
        }

        return redirect()->route('admin.backup.index')
            ->with('success', '✅ Configuration imported successfully.');
    }

    /**
     * Download a backup file.
     */
    public function download(string $filename)
    {
        $filePath = storage_path('app/backup/' . basename($filename));

        if (!file_exists($filePath)) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($filePath);
    }

    /**
     * Delete a backup file.
     */
    public function destroy(string $filename)
    {
        $filePath = storage_path('app/backup/' . basename($filename));

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return redirect()->route('admin.backup.index')
            ->with('success', 'Backup file deleted.');
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
