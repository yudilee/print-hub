<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\ClientApp;
use App\Models\DataSchema;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use App\Services\AgentSelectionService;
use App\Services\ContinuousFormEngine;
use App\Services\PrintJobOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientAppController extends Controller
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Retrieve the authenticated ClientApp injected by the AuthenticateApiKey middleware. */
    private function app(Request $request): ClientApp
    {
        return $request->attributes->get('client_app');
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/test
    // -------------------------------------------------------------------------

    public function testConnection(Request $request)
    {
        $app = $this->app($request);
        $onlineAgentCount = PrintAgent::where('is_active', true)->get()->filter->isOnline()->count();

        return ApiResponse::success([
            'message'     => 'Connected successfully.',
            'app_name'    => $app->name,
            'agents'      => $onlineAgentCount,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/health
    // -------------------------------------------------------------------------

    public function health(Request $request)
    {
        $onlineAgents  = PrintAgent::where('is_active', true)->get()->filter->isOnline()->count();
        $totalAgents   = PrintAgent::where('is_active', true)->count();
        $pendingJobs   = PrintJob::where('status', 'pending')->count();
        $processingJobs = PrintJob::where('status', 'processing')->count();

        return ApiResponse::success([
            'status'          => 'ok',
            'agents_online'   => $onlineAgents,
            'agents_total'    => $totalAgents,
            'jobs_pending'    => $pendingJobs,
            'jobs_processing' => $processingJobs,
            'server_time'     => now()->toIso8601String(),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/agents/online
    // -------------------------------------------------------------------------

    public function getOnlineAgents(Request $request)
    {
        $query = PrintAgent::with('branch:id,name,code')->where('is_active', true);

        if ($request->filled('branch_code')) {
            $branch = Branch::where('code', $request->branch_code)->first();
            if ($branch) {
                $query->where('branch_id', $branch->id);
            }
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $agents = $query->get()->filter->isOnline();

        $data = $agents->map(fn($a) => [
            'id'         => $a->id,
            'name'       => $a->name,
            'printers'   => $a->printers ?? [],
            'branch'     => $a->branch ? [
                'id'   => $a->branch->id,
                'code' => $a->branch->code,
                'name' => $a->branch->name,
            ] : null,
            'location'   => $a->location,
            'department' => $a->department,
        ])->values();

        return ApiResponse::success(['agents' => $data]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/branches
    // -------------------------------------------------------------------------

    public function listBranches(Request $request)
    {
        $branches = Branch::with('company:id,name,code')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn($b) => [
                'id'      => $b->id,
                'code'    => $b->code,
                'name'    => $b->name,
                'address' => $b->address,
                'company' => $b->company ? [
                    'id'   => $b->company->id,
                    'code' => $b->company->code,
                    'name' => $b->company->name,
                ] : null,
            ]);

        return ApiResponse::success(['branches' => $branches]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/queues
    // -------------------------------------------------------------------------

    public function listQueues(Request $request)
    {
        $query = PrintProfile::with('agent:id,name,last_seen_at,branch_id');

        if ($request->filled('branch_code')) {
            $branch = Branch::where('code', $request->branch_code)->first();
            if ($branch) {
                $query->where('branch_id', $branch->id);
            }
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $detailed = $request->boolean('detailed', false);

        $queues = $query->get()->map(function ($p) use ($detailed) {
            $result = [
                'name'        => $p->name,
                'description' => $p->description,
                'printer'     => $p->default_printer,
                'is_online'   => $p->agent ? $p->agent->isOnline() : false,
                'agent_name'  => $p->agent?->name,
                'branch_id'   => $p->branch_id,
            ];

            if ($detailed) {
                $result['paper_size']         = $p->paper_size;
                $result['orientation']        = $p->orientation;
                $result['copies']             = $p->copies;
                $result['duplex']             = $p->duplex;
                $result['margins']            = [
                    'top'    => $p->margin_top,
                    'bottom' => $p->margin_bottom,
                    'left'   => $p->margin_left,
                    'right'  => $p->margin_right,
                ];
                $result['tray_source']        = $p->tray_source;
                $result['color_mode']         = $p->color_mode;
                $result['print_quality']      = $p->print_quality;
                $result['scaling_percentage'] = $p->scaling_percentage;
                $result['media_type']         = $p->media_type;
                $result['collate']            = $p->collate;
                $result['reverse_order']      = $p->reverse_order;
            }

            return $result;
        });

        return ApiResponse::success(['queues' => $queues]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/templates
    // -------------------------------------------------------------------------

    public function listTemplates(Request $request)
    {
        $perPage   = min((int) $request->query('per_page', 25), 100);
        $paginator = PrintTemplate::orderBy('name')->paginate($perPage);

        $paginator->through(function ($t) {
            $elements = $t->elements ?? [];
            $fields   = collect($elements)->where('type', 'field')->pluck('key')->values();
            $tables   = collect($elements)->where('type', 'table')->map(fn($el) => [
                'key'     => $el['key'],
                'columns' => collect($el['columns'] ?? [])->map(fn($c) => [
                    'label' => $c['label'],
                    'key'   => $c['key'],
                ])->values(),
            ])->values();

            return [
                'name'            => $t->name,
                'paper_width_mm'  => $t->paper_width_mm,
                'paper_height_mm' => $t->paper_height_mm,
                'fields'          => $fields,
                'tables'          => $tables,
                'schema'          => $t->dataSchema ? [
                    'name'    => $t->dataSchema->schema_name,
                    'version' => $t->dataSchema->version,
                ] : null,
            ];
        });

        return ApiResponse::success([
            'templates' => $paginator->items(),
            'meta'      => [
                'current_page'  => $paginator->currentPage(),
                'per_page'      => $paginator->perPage(),
                'total'         => $paginator->total(),
                'last_page'     => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/templates/{name}
    // -------------------------------------------------------------------------

    public function getTemplate(Request $request, string $name)
    {
        $template = PrintTemplate::where('name', $name)->first();
        if (! $template) {
            return ApiResponse::notFound('TEMPLATE_NOT_FOUND', "Template '{$name}' not found.");
        }

        $elements = $template->elements ?? [];
        $fields   = collect($elements)->where('type', 'field')->map(fn($el) => [
            'key'       => $el['key'],
            'font_size' => $el['font_size'] ?? 10,
            'bold'      => $el['bold'] ?? false,
            'border'    => $el['border'] ?? false,
            'align'     => $el['align'] ?? 'L',
            'x'         => $el['x'],
            'y'         => $el['y'],
            'width'     => $el['width'],
            'height'    => $el['height'],
        ])->values();

        $tables = collect($elements)->where('type', 'table')->map(fn($el) => [
            'key'     => $el['key'],
            'x'       => $el['x'],
            'y'       => $el['y'],
            'columns' => collect($el['columns'] ?? [])->map(fn($c) => [
                'label' => $c['label'],
                'key'   => $c['key'],
                'width' => $c['width'],
            ])->values(),
        ])->values();

        return ApiResponse::success([
            'name'            => $template->name,
            'paper_width_mm'  => $template->paper_width_mm,
            'paper_height_mm' => $template->paper_height_mm,
            'fields'          => $fields,
            'tables'          => $tables,
            'schema'          => $template->dataSchema ? [
                'name'    => $template->dataSchema->schema_name,
                'version' => $template->dataSchema->version,
            ] : null,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/templates/{name}/schema
    // -------------------------------------------------------------------------

    public function getTemplateSchema(Request $request, string $name)
    {
        $template = PrintTemplate::with('dataSchema')->where('name', $name)->first();
        if (! $template) {
            return ApiResponse::notFound('TEMPLATE_NOT_FOUND', "Template '{$name}' not found.");
        }

        return ApiResponse::success($template->buildRequiredSchema());
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/schema
    // -------------------------------------------------------------------------

    public function registerSchema(Request $request)
    {
        $app  = $this->app($request);
        $data = $request->validate([
            'schema_name' => 'required|string|max:100',
            'label'       => 'nullable|string|max:255',
            'fields'      => 'nullable|array',
            'tables'      => 'nullable|array',
            'sample_data' => 'nullable|array',
        ]);

        $schemaName = $data['schema_name'];
        $existing   = DataSchema::forSchema($schemaName)->latest()->first();

        $hasChanges = true;
        if ($existing) {
            $hasChanges = (
                ($existing->fields ?? []) != ($data['fields'] ?? []) ||
                ($existing->tables ?? []) != ($data['tables'] ?? [])
            );
        }

        if ($hasChanges || ! $existing) {
            $schema = DataSchema::createNewVersion($schemaName, [
                'client_app_id' => $app->id,
                'label'         => $data['label'] ?? $data['schema_name'],
                'fields'        => $data['fields'] ?? [],
                'tables'        => $data['tables'] ?? [],
                'sample_data'   => $data['sample_data'] ?? null,
            ]);

            return ApiResponse::success([
                'schema_name' => $schema->schema_name,
                'version'     => $schema->version,
                'is_new'      => true,
                'message'     => "Schema v{$schema->version} created.",
            ], 201);
        }

        if (isset($data['sample_data'])) {
            $existing->update(['sample_data' => $data['sample_data']]);
        }

        return ApiResponse::success([
            'schema_name' => $existing->schema_name,
            'version'     => $existing->version,
            'is_new'      => false,
            'message'     => "No structural changes. Schema remains at v{$existing->version}.",
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/schemas
    // -------------------------------------------------------------------------

    public function listSchemas(Request $request)
    {
        $onlyLatest = $request->query('latest', 'true') !== 'false';
        $perPage    = min((int) $request->query('per_page', 25), 100);

        $query = DataSchema::with('clientApp:id,name');
        if ($onlyLatest) {
            $query->latest();
        }

        $paginator = $query->orderBy('schema_name')->orderByDesc('version')->paginate($perPage);

        $paginator->through(fn($s) => [
            'id'          => $s->id,
            'schema_name' => $s->schema_name,
            'version'     => $s->version,
            'is_latest'   => $s->is_latest,
            'label'       => $s->label,
            'client_app'  => $s->clientApp?->name,
            'fields'      => $s->fields,
            'tables'      => $s->tables,
            'has_sample'  => ! empty($s->sample_data),
            'changelog'   => $s->changelog,
            'updated_at'  => $s->updated_at?->toISOString(),
        ]);

        return ApiResponse::success([
            'schemas' => $paginator->items(),
            'meta'    => [
                'current_page'  => $paginator->currentPage(),
                'per_page'      => $paginator->perPage(),
                'total'         => $paginator->total(),
                'last_page'     => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/schema/{name}/versions
    // -------------------------------------------------------------------------

    public function schemaVersions(Request $request, string $name)
    {
        $versions = DataSchema::forSchema($name)
            ->orderByDesc('version')
            ->get()
            ->map(fn($s) => [
                'version'    => $s->version,
                'is_latest'  => $s->is_latest,
                'changelog'  => $s->changelog,
                'fields'     => array_keys($s->fields ?? []),
                'tables'     => array_keys($s->tables ?? []),
                'updated_at' => $s->updated_at?->toISOString(),
            ]);

        if ($versions->isEmpty()) {
            return ApiResponse::notFound('SCHEMA_NOT_FOUND', "Schema '{$name}' not found.");
        }

        return ApiResponse::success([
            'schema_name' => $name,
            'versions'    => $versions,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/print  (unified endpoint)
    // -------------------------------------------------------------------------

    public function unifiedPrint(Request $request)
    {
        $app  = $this->app($request);
        $data = $request->validate([
            'template'         => 'nullable|string',
            'data'             => 'nullable|array',
            'document_base64'  => 'nullable|string',
            'type'             => 'nullable|string',
            'agent_id'         => 'nullable|integer|exists:print_agents,id',
            'printer'          => 'nullable|string',
            'pool_id'          => 'nullable|integer|exists:printer_pools,id',
            'profile'          => 'nullable|string',
            'queue'            => 'nullable|string',
            'reference_id'     => 'nullable|string',
            'webhook_url'      => 'nullable|url',
            'options'          => 'nullable|array',
            'skip_validation'  => 'nullable|boolean',
            'branch_code'      => 'nullable|string',
            'branch_id'        => 'nullable|integer',
            'priority'         => 'nullable|integer|min:0|max:255',
            // Scheduling fields (Feature 1)
            'scheduled_at'     => 'nullable|date',
            'recurrence'       => 'nullable|string|in:daily,weekly,monthly,none',
            'recurrence_end_at'=> 'nullable|date',
            'recurrence_count' => 'nullable|integer|min:0',
            // Document field (Feature 2)
            'document_id'      => 'nullable|integer|exists:print_documents,id',
        ]);

        if (empty($data['template']) && empty($data['document_base64'])) {
            return ApiResponse::validationError(
                'Provide either "template" (with "data") or "document_base64".'
            );
        }

        // 0. Resolve Branch
        [$branch, $branchId, $branchError] = $this->resolveBranch($data);
        if ($branchError) return $branchError;

        // 1. Resolve Profile / Queue
        $profile     = null;
        $profileName = $data['queue'] ?? $data['profile'] ?? null;

        if ($profileName) {
            $profile = PrintProfile::with('agent')->where('name', $profileName)->first();
        }

        if (! $profile && $branch && ! empty($data['template'])) {
            $template = PrintTemplate::where('name', $data['template'])->first();
            if ($template) {
                $defaultProfile = $branch->getDefaultProfileForTemplate($template->id);
                if ($defaultProfile) {
                    $profile     = $defaultProfile;
                    $profileName = $profile->name;
                }
            }
        }

        // 2. Merge options
        $options = PrintJobOrchestrator::mergeProfileOptions($profile, $data['options'] ?? []);

        // 3. Select agent
        try {
            $agent = AgentSelectionService::select(
                $data['agent_id'] ?? null,
                $profile,
                $branchId,
                $profileName
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::serviceUnavailable('NO_AGENT_AVAILABLE', $e->getMessage());
        }

        // 4. Resolve printer (pool_id takes precedence over printer_name)
        $poolId = $data['pool_id'] ?? null;
        if ($poolId) {
            try {
                $printer = $orchestrator->selectPrinterFromPool((int) $poolId, $agent->id);
            } catch (\RuntimeException $e) {
                return ApiResponse::serviceUnavailable('POOL_ERROR', $e->getMessage());
            }
        } else {
            $printer = PrintJobOrchestrator::resolvePrinter($data['printer'] ?? null, $profile);
        }

        // 5. Generate document
        $orchestrator        = new PrintJobOrchestrator();
        $validationWarnings  = [];

        if (! empty($data['template'])) {
            try {
                $result = $orchestrator->generateFromTemplate(
                    $data['template'],
                    $data['data'] ?? [],
                    $options,
                    $data['skip_validation'] ?? false
                );
            } catch (\RuntimeException $e) {
                return ApiResponse::notFound('TEMPLATE_NOT_FOUND', $e->getMessage());
            }
            $filePath           = $result['filePath'];
            $type               = $result['type'];
            $templateName       = $result['templateName'];
            $validationWarnings = $result['validationWarnings'];
        } else {
            try {
                $result = $orchestrator->generateFromBase64($data['document_base64'], $data['type'] ?? null);
            } catch (\RuntimeException $e) {
                return ApiResponse::validationError($e->getMessage());
            }
            $filePath     = $result['filePath'];
            $type         = $result['type'];
            $templateName = null;
        }

        // Validate document_id if provided (Feature 2)
        $documentId = $data['document_id'] ?? null;
        if ($documentId) {
            $document = \App\Models\PrintDocument::find($documentId);
            if (!$document) {
                return ApiResponse::notFound('DOCUMENT_NOT_FOUND', 'Document not found.');
            }
        }

        // 6. Create job record
        $orchestrator->createJob(
            $filePath,
            $agent,
            $branchId,
            $printer,
            $type,
            $options,
            $data['webhook_url'] ?? null,
            $data['reference_id'] ?? null,
            $templateName,
            ! empty($data['template']) ? ($data['data'] ?? null) : null,
            (int) ($data['priority'] ?? 0),
            documentId: $documentId,
            scheduledAt: $data['scheduled_at'] ?? null,
            recurrence: $data['recurrence'] ?? null,
            recurrenceEndAt: $data['recurrence_end_at'] ?? null,
            recurrenceCount: $data['recurrence_count'] ?? null,
            poolId: $poolId,
        );

        $jobId = pathinfo($filePath, PATHINFO_FILENAME);

        $responseData = [
            'status'            => 'queued',
            'job_id'            => $jobId,
            'agent'             => $agent->name,
            'printer'           => $printer,
            'template'          => $templateName,
            'priority'          => (int) ($data['priority'] ?? 0),
            'queue'             => $profile ? $profile->name : null,
            'scheduled_at'      => $data['scheduled_at'] ?? null,
            'recurrence'        => $data['recurrence'] ?? null,
            'document_id'       => $documentId,
        ];

        if (! empty($validationWarnings)) {
            $responseData['warnings'] = $validationWarnings;
        }

        return ApiResponse::success($responseData, 202);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/jobs  (legacy submit — kept for backwards compat)
    // -------------------------------------------------------------------------

    public function submitJob(Request $request)
    {
        if ($request->has('template_data') && ! $request->has('data')) {
            $request->merge(['data' => $request->input('template_data')]);
        }

        return $this->unifiedPrint($request);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/jobs/{job_id}
    // -------------------------------------------------------------------------

    public function jobStatus(Request $request, string $jobId)
    {
        $job = PrintJob::where('job_id', $jobId)->first();
        if (! $job) {
            return ApiResponse::notFound('JOB_NOT_FOUND', 'Job not found.');
        }

        return ApiResponse::success([
            'job_id'       => $job->job_id,
            'status'       => $job->status,
            'priority'     => $job->priority,
            'reference_id' => $job->reference_id,
            'printer'      => $job->printer_name,
            'template'     => $job->template_name,
            'error'        => $job->error,
            'created_at'   => $job->created_at?->toISOString(),
            'completed_at' => $job->agent_completed_at?->toISOString(),
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/jobs/{job_id}
    // -------------------------------------------------------------------------

    public function cancelJob(Request $request, string $jobId)
    {
        $job = PrintJob::where('job_id', $jobId)->first();
        if (! $job) {
            return ApiResponse::notFound('JOB_NOT_FOUND', 'Job not found.');
        }

        if (! in_array($job->status, ['pending'])) {
            return ApiResponse::error(
                'JOB_NOT_CANCELLABLE',
                "Job cannot be cancelled in status '{$job->status}'. Only 'pending' jobs can be cancelled.",
                409
            );
        }

        $job->update(['status' => 'cancelled']);

        return ApiResponse::success([
            'job_id'  => $job->job_id,
            'status'  => 'cancelled',
            'message' => 'Job cancelled successfully.',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/preview
    // -------------------------------------------------------------------------

    public function previewPrint(Request $request)
    {
        $data = $request->validate([
            'template' => 'required|string',
            'data'     => 'nullable|array',
            'options'  => 'nullable|array',
        ]);

        $template = PrintTemplate::where('name', $data['template'])->first();
        if (! $template) {
            return ApiResponse::notFound('TEMPLATE_NOT_FOUND', 'Template not found.');
        }

        $engine    = new ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $data['data'] ?? [], $data['options'] ?? []);

        return response($pdfBinary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/print/batch
    // -------------------------------------------------------------------------

    public function batchPrint(Request $request)
    {
        $app = $this->app($request);

        $validated = $request->validate([
            'jobs'                   => 'required|array|min:1|max:50',
            'jobs.*.template'        => 'nullable|string',
            'jobs.*.data'            => 'nullable|array',
            'jobs.*.document_base64' => 'nullable|string',
            'jobs.*.printer'         => 'nullable|string',
            'jobs.*.queue'           => 'nullable|string',
            'jobs.*.reference_id'    => 'nullable|string',
            'jobs.*.branch_code'     => 'nullable|string',
            'jobs.*.branch_id'       => 'nullable|integer',
            'jobs.*.options'         => 'nullable|array',
            'dry_run'                => 'nullable|boolean',
        ]);

        $isDryRun  = $validated['dry_run'] ?? false;
        $batchId   = (string) Str::uuid();
        $results   = [];
        $allValid  = true;

        // Phase 1: Validate all jobs first (always, for dry_run & real runs alike)
        foreach ($validated['jobs'] as $index => $jobData) {
            $jobRequest = Request::create('/api/v1/print', 'POST', $jobData);
            $jobRequest->attributes->set('client_app', $app);

            if (empty($jobData['template']) && empty($jobData['document_base64'])) {
                $results[$index] = [
                    'index'     => $index,
                    'success'   => false,
                    'error'     => ['code' => 'VALIDATION_FAILED', 'message' => 'Provide "template" or "document_base64".'],
                    'reference' => $jobData['reference_id'] ?? null,
                ];
                $allValid = false;
                continue;
            }

            $results[$index] = ['index' => $index, 'success' => true, 'reference' => $jobData['reference_id'] ?? null];
        }

        if ($isDryRun) {
            return ApiResponse::success([
                'dry_run'   => true,
                'batch_id'  => $batchId,
                'all_valid' => $allValid,
                'results'   => array_values($results),
            ]);
        }

        if (! $allValid) {
            return ApiResponse::validationError(
                'One or more jobs failed validation. Use "dry_run": true to check before submitting.',
                ['results' => array_values($results)]
            );
        }

        // Phase 2: Queue all jobs atomically
        DB::beginTransaction();
        try {
            foreach ($validated['jobs'] as $index => $jobData) {
                $jobRequest = Request::create('/api/v1/print', 'POST', $jobData);
                $jobRequest->attributes->set('client_app', $app);

                $response    = $this->unifiedPrint($jobRequest);
                $body        = json_decode($response->getContent(), true);

                $results[$index] = [
                    'index'     => $index,
                    'success'   => $body['success'] ?? false,
                    'job_id'    => $body['data']['job_id'] ?? null,
                    'error'     => $body['error'] ?? null,
                    'reference' => $jobData['reference_id'] ?? null,
                ];

                if (! ($body['success'] ?? false)) {
                    throw new \RuntimeException("Job #{$index} failed: " . ($body['error']['message'] ?? 'Unknown error'));
                }
            }

            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return ApiResponse::error('BATCH_FAILED', $e->getMessage(), 422);
        }

        return ApiResponse::success([
            'batch_id' => $batchId,
            'total'    => count($results),
            'results'  => array_values($results),
        ], 202);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve branch from request data.
     * Returns [Branch|null, int|null, JsonResponse|null].
     */
    private function resolveBranch(array $data): array
    {
        if (! empty($data['branch_code'])) {
            $branch = Branch::where('code', $data['branch_code'])->first();
            if (! $branch) {
                return [null, null, ApiResponse::notFound('BRANCH_NOT_FOUND', "Branch '{$data['branch_code']}' not found.")];
            }
            return [$branch, $branch->id, null];
        }

        if (! empty($data['branch_id'])) {
            $branch = Branch::find($data['branch_id']);
            if (! $branch) {
                return [null, null, ApiResponse::notFound('BRANCH_NOT_FOUND', "Branch ID {$data['branch_id']} not found.")];
            }
            return [$branch, $branch->id, null];
        }

        return [null, null, null];
    }
}
