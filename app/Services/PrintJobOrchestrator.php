<?php

namespace App\Services;

use App\Models\PrintAgent;
use App\Models\PrintApprovalRule;
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
    ): PrintJob {
        $jobId = pathinfo($filePath, PATHINFO_FILENAME);

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
            'options'          => $options,
            'template_data'    => $templateData,
            'template_name'    => $templateName,
            'scheduled_at'     => $scheduledAt,
            'recurrence'       => $recurrence,
            'recurrence_end_at' => $recurrenceEndAt,
            'recurrence_count'  => $recurrenceCount,
            'pool_id'          => $poolId,
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
                // Failover: return the highest priority active printer
                $first = $printers->first();
                if (!$first) {
                    throw new \RuntimeException("Failover pool '{$pool->name}' has no active printers.");
                }
                return $first->printer_name;

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
