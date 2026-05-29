<?php

namespace App\Services;

use App\Models\PrintAgent;
use App\Models\PrintApprovalRule;
use App\Models\PrinterConfig;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Models\PrinterPool;
use App\Models\PrintTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrintJobOrchestrator
{
    /**
     * Generate a PDF from template + data and store it.
     *
     * @return array{filePath: string, type: string, templateName: string|null, validationWarnings: array}
     */
    public function generateFromTemplate(string $templateName, array $printData, array $options = [], bool $skipValidation = false): array
    {
        $template = PrintTemplate::with('dataSchema')->where('name', $templateName)->first();

        if (!$template) {
            throw new \RuntimeException("Template '{$templateName}' not found.");
        }

        $validationWarnings = [];

        if ($template->dataSchema && !$skipValidation) {
            $errors = $template->dataSchema->validateData($printData);
            if (!empty($errors)) {
                $validationWarnings = $errors;
            }
        }

        $engine = new ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $printData, $options);

        $jobId = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.pdf";

        Storage::put($filePath, $pdfBinary);

        return [
            'filePath'           => $filePath,
            'type'               => 'pdf',
            'templateName'       => $template->name,
            'validationWarnings' => $validationWarnings,
        ];
    }

    /**
     * Decode and store a base64-encoded document.
     *
     * @return array{filePath: string, type: string}
     */
    public function generateFromBase64(string $base64Data, ?string $type = null): array
    {
        $base64Data = preg_replace('/\s+/', '', $base64Data);

        if (strlen($base64Data) % 4 === 1) {
            throw new \RuntimeException('Invalid base64 string length.');
        }

        $decoded = base64_decode($base64Data, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64-encoded content.');
        }

        $resolvedType = $type ?? 'pdf';
        $extension = ($resolvedType === 'pdf') ? 'pdf' : 'raw';
        $jobId = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.{$extension}";

        Storage::put($filePath, $decoded);

        return [
            'filePath' => $filePath,
            'type'     => $resolvedType,
        ];
    }

    /**
     * Create a PrintJob record in the database.
     *
     * Printer Config overrides are applied automatically — options passed
     * in $options (job-level) take highest priority, followed by the
     * per-printer config, then the profile defaults.
     */
    public function createJob(
        string $filePath,
        PrintAgent $agent,
        ?int $branchId,
        string $printer,
        string $type,
        array $options = [],
        ?string $webhookUrl = null,
        ?string $referenceId = null,
        ?string $templateName = null,
        ?array $templateData = null,
        int $priority = 0,
        ?int $documentId = null,
        ?string $scheduledAt = null,
        ?string $recurrence = null,
        ?string $recurrenceEndAt = null,
        ?int $recurrenceCount = null,
        ?int $poolId = null,
        ?int $dependsOnJobId = null,
        ?string $dependencyType = null,
    ): PrintJob {
        $jobId = pathinfo($filePath, PATHINFO_FILENAME);

        // ── Printer Config Override ───────────────────────────
        // Apply per-printer overrides BEFORE storing options in the DB.
        // Priority: job-level options > printer config > profile defaults.
        $mergedOptions = self::applyPrinterConfigOverrides($options, $agent->id, $printer);

        $data = [
            'job_id'           => $jobId,
            'print_agent_id'   => $agent->id,
            'branch_id'        => $branchId,
            'document_id'      => $documentId,
            'printer_name'     => $printer,
            'type'             => $type,
            'priority'         => $priority,
            'status'           => 'pending',
            'file_path'        => $filePath,
            'webhook_url'      => $webhookUrl,
            'reference_id'     => $referenceId,
            'options'          => $mergedOptions,
            'template_data'    => $templateData,
            'template_name'    => $templateName,
            'scheduled_at'     => $scheduledAt,
            'recurrence'       => $recurrence,
            'recurrence_end_at' => $recurrenceEndAt,
            'recurrence_count'  => $recurrenceCount,
            'pool_id'          => $poolId,
            'depends_on_job_id' => $dependsOnJobId,
            'dependency_type'   => $dependencyType,
        ];

        // Check approval rules before creating the job
        $approvalCheck = $this->checkApprovalRules($templateData, $options, $agent);
        if ($approvalCheck['requires_approval']) {
            $data['requires_approval'] = true;
            $data['approval_status']   = 'pending';
            $data['status']            = 'pending'; // stays pending until approved
        }

        $job = PrintJob::create($data);

        // Dispatch job status event for new job
        event(new \App\Events\JobStatusUpdated($job));

        // Dispatch queue update for admin dashboard
        $this->dispatchQueueUpdated();

        return $job;
    }

    /**
     * Check whether a job's dependencies have been satisfied so it can be dispatched.
     *
     * @param PrintJob $job The job to check.
     * @return array{ready: bool, reason: string|null}
     */
    public function checkDependencies(PrintJob $job): array
    {
        if (!$job->depends_on_job_id) {
            return ['ready' => true, 'reason' => null];
        }

        $dependency = PrintJob::find($job->depends_on_job_id);

        if (!$dependency) {
            return ['ready' => false, 'reason' => 'Dependency job no longer exists.'];
        }

        switch ($job->dependency_type) {
            case 'after':
                // Ready once the dependency has any terminal status
                if (in_array($dependency->status, ['success', 'failed'])) {
                    return ['ready' => true, 'reason' => null];
                }
                return ['ready' => false, 'reason' => "Dependency job {$dependency->job_id} is still {$dependency->status}."];

            case 'after_success':
                if ($dependency->status === 'success') {
                    return ['ready' => true, 'reason' => null];
                }
                if ($dependency->status === 'failed') {
                    return ['ready' => false, 'reason' => "Dependency job {$dependency->job_id} failed."];
                }
                return ['ready' => false, 'reason' => "Dependency job {$dependency->job_id} is still {$dependency->status}."];

            case 'after_failure':
                if ($dependency->status === 'failed') {
                    return ['ready' => true, 'reason' => null];
                }
                if ($dependency->status === 'success') {
                    return ['ready' => false, 'reason' => "Dependency job {$dependency->job_id} succeeded, not a failure."];
                }
                return ['ready' => false, 'reason' => "Dependency job {$dependency->job_id} is still {$dependency->status}."];

            default:
                return ['ready' => false, 'reason' => "Unknown dependency type: {$job->dependency_type}."];
        }
    }

    /**
     * Dispatch QueueUpdated event for admin dashboard.
     */
    private function dispatchQueueUpdated(): void
    {
        try {
            $queueData = [
                'total_pending'    => PrintJob::where('status', 'pending')->count(),
                'total_processing' => PrintJob::where('status', 'processing')->count(),
                'total_queued'     => PrintJob::whereIn('status', ['pending', 'processing'])->count(),
            ];
            event(new \App\Events\QueueUpdated($queueData));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to dispatch QueueUpdated event: ' . $e->getMessage());
        }
    }

    /**
     * Check if the job requires approval based on configured rules.
     *
     * @return array{requires_approval: bool, rule: PrintApprovalRule|null}
     */
    public function checkApprovalRules(?array $templateData, array $options, PrintAgent $agent): array
    {
        $rules = PrintApprovalRule::where('active', true)->get();

        foreach ($rules as $rule) {
            switch ($rule->rule_type) {
                case 'user':
                    // Match by user ID (if user context is available)
                    $userId = Auth::id();
                    if ($userId && (string) $userId === $rule->rule_value) {
                        return ['requires_approval' => $rule->requires_approval, 'rule' => $rule];
                    }
                    break;

                case 'role':
                    // Match by user role
                    if (Auth::check() && Auth::user()->role === $rule->rule_value) {
                        return ['requires_approval' => $rule->requires_approval, 'rule' => $rule];
                    }
                    break;

                case 'page_count':
                    // Match by page count from options or template data
                    $pageCount = (int) ($options['page_count'] ?? $templateData['page_count'] ?? 0);
                    if ($pageCount >= (int) $rule->rule_value) {
                        return ['requires_approval' => $rule->requires_approval, 'rule' => $rule];
                    }
                    break;

                case 'cost':
                    // Match by estimated cost
                    $cost = (float) ($options['estimated_cost'] ?? 0);
                    if ($cost >= (float) $rule->rule_value) {
                        return ['requires_approval' => $rule->requires_approval, 'rule' => $rule];
                    }
                    break;
            }
        }

        return ['requires_approval' => false, 'rule' => null];
    }

    /**
     * Apply per-printer configuration overrides to job options.
     *
     * Looks up an active PrinterConfig for the given agent + printer,
     * then merges its config into $options. Job-level options always
     * take priority (they are applied on top of the printer config).
     *
     * Priority chain:
     *   1. $options (job-level, from request) — highest
     *   2. PrinterConfig.config (per-printer override) — medium
     *   3. Profile defaults (already resolved before this call) — lowest
     *
     * @param  array  $options      Job-level options (already merged with profile defaults)
     * @param  int    $agentId      Print agent ID
     * @param  string $printerName  Printer name on the agent
     * @return array
     */
    public static function applyPrinterConfigOverrides(array $options, int $agentId, string $printerName): array
    {
        $config = PrinterConfig::where('print_agent_id', $agentId)
            ->where('printer_name', $printerName)
            ->where('is_active', true)
            ->first();

        if (! $config || empty($config->config)) {
            return $options;
        }

        // Merge: printer config first, then job-level options on top so they win
        return array_merge($config->config, $options);
    }

    /**
     * Resolve printer name from request, profile, or fallback.
     */
    public static function resolvePrinter(?string $requestedPrinter, ?PrintProfile $profile): string
    {
        if ($requestedPrinter) {
            return $requestedPrinter;
        }

        if ($profile && $profile->default_printer) {
            return $profile->default_printer;
        }

        $p = PrintProfile::first();
        return $p?->default_printer ?? 'Default';
    }

    /**
     * Build print options by merging profile defaults with request options.
     */
    public static function mergeProfileOptions(?PrintProfile $profile, array $requestOptions = []): array
    {
        $options = [];

        if ($profile) {
            $options = [
                'orientation'    => $profile->orientation,
                'copies'         => $profile->copies,
                'duplex'         => $profile->duplex,
                'margin_top'     => $profile->margin_top,
                'margin_bottom'  => $profile->margin_bottom,
                'margin_left'    => $profile->margin_left,
                'margin_right'   => $profile->margin_right,
                'fit_to_page'    => $profile->extra_options['fit_to_page'] ?? false,
                // Watermark fields
                'watermark_text'        => $profile->watermark_text,
                'watermark_opacity'     => $profile->watermark_opacity ?? 0.3,
                'watermark_rotation'    => $profile->watermark_rotation ?? -45,
                'watermark_position'    => $profile->watermark_position ?? 'center',
                'watermark_copies'  => $profile->watermark_copies ?? [],
            ];

            $dimensions = PaperSizeService::resolveFromProfile($profile);
            $options['paper_width_mm']  = $dimensions['width_mm'];
            $options['paper_height_mm'] = $dimensions['height_mm'];
        }

        return array_merge($options, $requestOptions);
    }

    /**
     * Select a printer from a pool based on the configured strategy.
     *
     * @return string The selected printer name.
     * @throws \RuntimeException If no printer can be selected.
     */
    public function selectPrinterFromPool(int $poolId, ?int $agentId = null): string
    {
        $pool = PrinterPool::with(['activePrinters'])->findOrFail($poolId);

        if (!$pool->active) {
            throw new \RuntimeException("Printer pool '{$pool->name}' is inactive.");
        }

        $printers = $pool->activePrinters;

        if ($printers->isEmpty()) {
            throw new \RuntimeException("No active printers in pool '{$pool->name}'.");
        }

        switch ($pool->strategy) {
            case 'round_robin':
                return $this->roundRobinSelect($pool, $printers);

            case 'least_busy':
                return $this->leastBusySelect($printers, $agentId);

            case 'random':
                return $printers->random()->printer_name;

            case 'failover':
                // Pick the highest-priority printer that is healthy
                $healthy = $printers->first(fn($p) => ($p->is_healthy ?? true));
                if (!$healthy) {
                    // All unhealthy — reset and try again
                    \App\Models\PrinterPoolPrinter::where('pool_id', $pool->id)
                        ->update(['is_healthy' => true, 'failure_count' => 0]);
                    $healthy = $printers->first();
                }
                if (!$healthy) {
                    throw new \RuntimeException("Failover pool '{$pool->name}' has no active printers.");
                }
                return $healthy->printer_name;

            default:
                return $printers->first()->printer_name;
        }
    }

    /**
     * Round-robin selection: cycle through printers using a cache key.
     */
    private function roundRobinSelect(PrinterPool $pool, $printers): string
    {
        $cacheKey = "pool_round_robin:{$pool->id}";
        $lastIndex = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, -1);
        $nextIndex = ($lastIndex + 1) % $printers->count();

        \Illuminate\Support\Facades\Cache::put($cacheKey, $nextIndex, now()->addDay());

        return $printers->get($nextIndex)->printer_name;
    }

    /**
     * Least-busy selection: pick the printer with the fewest pending jobs.
     */
    private function leastBusySelect($printers, ?int $agentId): string
    {
        $busyCounts = [];
        foreach ($printers as $pp) {
            $query = PrintJob::where('printer_name', $pp->printer_name)
                ->whereIn('status', ['pending', 'processing']);

            if ($agentId) {
                $query->where('print_agent_id', $agentId);
            }

            $busyCounts[$pp->printer_name] = $query->count();
        }

        // Sort by busiest (ascending)
        asort($busyCounts);

        return array_key_first($busyCounts);
    }
}
