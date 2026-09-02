<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\ClientApp;
use App\Models\Connector;
use App\Models\DataSchema;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use App\Services\AgentSelectionService;
use App\Services\ContinuousFormEngine;
use App\Services\PrintJobOrchestrator;
use App\Services\WebhookService;
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
    // GET /api/v1/printers
    // -------------------------------------------------------------------------

    public function listPrinters(Request $request)
    {
        $query = PrintAgent::with('branch:id,name,code')->where('is_active', true);

        if ($request->filled('branch_code')) {
            $branch = Branch::where('code', $request->branch_code)->first();
            if ($branch) {
                $query->where('branch_id', $branch->id);
            }
        }

        $agents = $query->get();
        $printersList = [];

        foreach ($agents as $agent) {
            $isAgentOnline = $agent->isOnline();
            $printers = $agent->printers ?? [];
            foreach ($printers as $p) {
                $printerName = is_array($p) ? ($p['name'] ?? 'Unknown') : (string)$p;
                $isDefault = is_array($p) ? ($p['is_default'] ?? false) : false;
                $status = is_array($p) ? ($p['status'] ?? ($isAgentOnline ? 'online' : 'offline')) : ($isAgentOnline ? 'online' : 'offline');
                $paperStatus = is_array($p) ? ($p['paper_status'] ?? 'ok') : 'ok';
                $capabilities = is_array($p) ? ($p['capabilities'] ?? []) : [];

                $printersList[] = [
                    'name'          => $printerName,
                    'agent_id'      => $agent->id,
                    'agent_name'    => $agent->name,
                    'agent_online'  => $isAgentOnline,
                    'branch'        => $agent->branch ? [
                        'id'   => $agent->branch->id,
                        'code' => $agent->branch->code,
                        'name' => $agent->branch->name,
                    ] : null,
                    'status'        => $isAgentOnline ? $status : 'offline',
                    'paper_status'  => $paperStatus,
                    'is_default'    => $isDefault,
                    'capabilities'  => $capabilities,
                ];
            }
        }

        return ApiResponse::success(['printers' => $printersList]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/pools
    // -------------------------------------------------------------------------

    public function listPools(Request $request)
    {
        $pools = \App\Models\PrinterPool::with(['branch:id,name,code'])
            ->where('is_active', true)
            ->get()
            ->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'strategy' => $p->strategy,
                'branch'   => $p->branch ? [
                    'id'   => $p->branch->id,
                    'code' => $p->branch->code,
                    'name' => $p->branch->name,
                ] : null,
                'printers' => $p->printers ?? [],
            ]);

        return ApiResponse::success(['pools' => $pools]);
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
        $paginator = PrintTemplate::with(['dataSchema', 'schemas.clientApp'])->orderBy('name')->paginate($perPage);

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
            $barcodes = collect($elements)->where('type', 'barcode')->map(fn($el) => [
                'value'     => $el['value'] ?? '',
                'symbology' => $el['symbology'] ?? 'code128',
            ])->values();
            $qrcodes  = collect($elements)->where('type', 'qrcode')->map(fn($el) => [
                'value'           => $el['value'] ?? '',
                'errorCorrection' => $el['errorCorrection'] ?? 'M',
            ])->values();

            // Build schemas array from both legacy and pivot sources
            $schemas = collect();
            if ($t->dataSchema) {
                $schemas->push([
                    'id'              => $t->dataSchema->id,
                    'name'            => $t->dataSchema->schema_name,
                    'version'         => $t->dataSchema->version,
                    'alias'           => null,
                    'is_primary'      => true,
                    'client_app_name' => $t->dataSchema->clientApp?->name,
                ]);
            }
            foreach ($t->schemas as $schema) {
                if ($schema->id === $t->data_schema_id) continue;
                $schemas->push([
                    'id'              => $schema->id,
                    'name'            => $schema->schema_name,
                    'version'         => $schema->version,
                    'alias'           => $schema->pivot->alias,
                    'is_primary'      => false,
                    'client_app_name' => $schema->clientApp?->name,
                ]);
            }

            return [
                'name'            => $t->name,
                'paper_width_mm'  => $t->paper_width_mm,
                'paper_height_mm' => $t->paper_height_mm,
                'fields'          => $fields,
                'tables'          => $tables,
                'barcodes'        => $barcodes,
                'qrcodes'         => $qrcodes,
                'schema'          => $t->dataSchema ? [
                    'name'    => $t->dataSchema->schema_name,
                    'version' => $t->dataSchema->version,
                ] : null,
                'schemas'         => $schemas->values()->all(),
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
        $template = PrintTemplate::where('name', $name)
            ->with(['dataSchema', 'schemas.clientApp'])
            ->first();
        if (! $template) {
            return ApiResponse::notFound('TEMPLATE_NOT_FOUND', "Template '{$name}' not found.");
        }

        $elements = $template->elements ?? [];
        $fields   = collect($elements)->where('type', 'field')->map(fn($el) => [
            'key'                => $el['key'],
            'font_size'          => $el['font_size'] ?? 10,
            'bold'               => $el['bold'] ?? false,
            'border'             => $el['border'] ?? false,
            'align'              => $el['align'] ?? 'L',
            'x'                  => $el['x'],
            'y'                  => $el['y'],
            'width'              => $el['width'],
            'height'             => $el['height'],
            'conditionalFormats' => $el['conditionalFormats'] ?? [],
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

        $barcodes = collect($elements)->where('type', 'barcode')->map(fn($el) => [
            'value'     => $el['value'] ?? '',
            'symbology' => $el['symbology'] ?? 'code128',
            'x'         => $el['x'],
            'y'         => $el['y'],
            'width'     => $el['width'],
        ])->values();

        $qrcodes  = collect($elements)->where('type', 'qrcode')->map(fn($el) => [
            'value'           => $el['value'] ?? '',
            'errorCorrection' => $el['errorCorrection'] ?? 'M',
            'x'               => $el['x'],
            'y'               => $el['y'],
            'size'            => $el['size'] ?? 25,
        ])->values();

        // Build schemas array from both legacy and pivot sources
        $schemas = collect();
        if ($template->dataSchema) {
            $schemas->push([
                'id'              => $template->dataSchema->id,
                'name'            => $template->dataSchema->schema_name,
                'version'         => $template->dataSchema->version,
                'alias'           => null,
                'is_primary'      => true,
                'client_app_name' => $template->dataSchema->clientApp?->name,
            ]);
        }
        foreach ($template->schemas as $schema) {
            if ($schema->id === $template->data_schema_id) continue;
            $schemas->push([
                'id'              => $schema->id,
                'name'            => $schema->schema_name,
                'version'         => $schema->version,
                'alias'           => $schema->pivot->alias,
                'is_primary'      => false,
                'client_app_name' => $schema->clientApp?->name,
            ]);
        }

        return ApiResponse::success([
            'name'            => $template->name,
            'paper_width_mm'  => $template->paper_width_mm,
            'paper_height_mm' => $template->paper_height_mm,
            'fields'          => $fields,
            'tables'          => $tables,
            'barcodes'        => $barcodes,
            'qrcodes'         => $qrcodes,
            'schema'          => $template->dataSchema ? [
                'name'    => $template->dataSchema->schema_name,
                'version' => $template->dataSchema->version,
            ] : null,
            'schemas'         => $schemas->values()->all(),
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
    // GET /api/v1/schemas/{name}/diff
    // -------------------------------------------------------------------------

    public function schemaVersionDiff(Request $request, string $name)
    {
        $app = $this->app($request);

        $schema = DataSchema::where('schema_name', $name)
            ->where('client_app_id', $app->id)
            ->latest('version')
            ->first();

        if (!$schema) {
            return response()->json(['success' => false, 'error' => 'Schema not found'], 404);
        }

        $fromVersion = $request->input('from_version', $schema->version - 1);
        $toVersion = $request->input('to_version', $schema->version);

        $fromSchema = DataSchema::where('schema_name', $name)
            ->where('client_app_id', $app->id)
            ->where('version', $fromVersion)
            ->first();

        $toSchema = DataSchema::where('schema_name', $name)
            ->where('client_app_id', $app->id)
            ->where('version', $toVersion)
            ->first();

        if (!$fromSchema || !$toSchema) {
            return response()->json(['success' => false, 'error' => 'Requested schema versions not found'], 404);
        }

        $oldKeys = $fromSchema->getFieldKeys();
        $newKeys = $toSchema->getFieldKeys();

        $added = array_diff($newKeys, $oldKeys);
        $removed = array_diff($oldKeys, $newKeys);
        $common = array_intersect($oldKeys, $newKeys);

        // Detect type changes
        $changed = [];
        $oldStructure = $fromSchema->getTableStructure();
        $newStructure = $toSchema->getTableStructure();

        foreach ($common as $key) {
            // Parse key to find its type in both versions
            $parts = explode('.', $key);
            if (count($parts) === 2) {
                $table = $parts[0];
                $col = $parts[1];
                $oldType = $oldStructure[$table][$col]['type'] ?? null;
                $newType = $newStructure[$table][$col]['type'] ?? null;
                if ($oldType && $newType && $oldType !== $newType) {
                    $changed[] = [
                        'field' => $key,
                        'old_type' => $oldType,
                        'new_type' => $newType,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'schema_name' => $name,
            'from_version' => (int) $fromVersion,
            'to_version' => (int) $toVersion,
            'diff' => [
                'added' => array_values($added),
                'removed' => array_values($removed),
                'changed' => $changed,
            ],
            'summary' => [
                'added_count' => count($added),
                'removed_count' => count($removed),
                'changed_count' => count($changed),
                'total_before' => count($oldKeys),
                'total_after' => count($newKeys),
            ],
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
            'parameters'       => 'nullable|array',
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
            'expires_at'       => 'nullable|date',
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
        $orchestrator = new PrintJobOrchestrator();
        $poolId = $data['pool_id'] ?? ($profile ? $profile->pool_id : null);
        if ($poolId) {
            try {
                $printer = $orchestrator->selectPrinterFromPool((int) $poolId, $agent->id);
            } catch (\RuntimeException $e) {
                return ApiResponse::serviceUnavailable('POOL_ERROR', $e->getMessage());
            }
        } else {
            $printer = PrintJobOrchestrator::resolvePrinter($data['printer'] ?? null, $profile);
        }

        // 4b. Resolve runtime parameters if a template is specified
        $printData = $data['data'] ?? [];
        if (! empty($data['template'])) {
            $templateModel = PrintTemplate::where('name', $data['template'])->first();
            if ($templateModel && $templateModel->exists) {
                $printData = $templateModel->resolveParameters(
                     $data['parameters'] ?? [],
                     $printData
                );
            }
        }

        // 5. Generate document
        $validationWarnings  = [];

        if (! empty($data['template'])) {
            try {
                $result = $orchestrator->generateFromTemplate(
                    $data['template'],
                    $printData,
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
            expiresAt: $data['expires_at'] ?? null,
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
            'expires_at'        => $data['expires_at'] ?? null,
            'recurrence'        => $data['recurrence'] ?? null,
            'document_id'       => $documentId,
        ];

        if (! empty($validationWarnings)) {
            $responseData['warnings'] = $validationWarnings;
        }

        return ApiResponse::success($responseData, 202);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/print/odoo-report
    // -------------------------------------------------------------------------

    public function printOdooReport(Request $request)
    {
        $app = $this->app($request);
        $data = $request->validate([
            'connector_id' => 'required|string|exists:connectors,id',
            'report_name'  => 'required|string',
            'record_ids'   => 'required|array|min:1',
            'record_ids.*' => 'required|integer',
            'branch_code'  => 'nullable|string',
            'branch_id'    => 'nullable|integer',
            'queue'        => 'nullable|string',
            'profile'      => 'nullable|string',
            'printer'      => 'nullable|string',
            'reference_id' => 'nullable|string',
            'options'      => 'nullable|array',
            'priority'     => 'nullable|integer|min:0|max:255',
        ]);

        $connector = Connector::where('id', $data['connector_id'])
            ->where('client_app_id', $app->id)
            ->first();

        if (! $connector || $connector->type !== 'odoo') {
            return ApiResponse::error('INVALID_CONNECTOR', 'The specified connector is not an active Odoo connector for this application.', 422);
        }

        $odooConfig = $connector->config ?? [];
        $url      = $odooConfig['url'] ?? $odooConfig['endpoint_url'] ?? null;
        $db       = $odooConfig['db'] ?? $odooConfig['database'] ?? null;
        $login    = $odooConfig['login'] ?? $odooConfig['username'] ?? null;
        $password = $odooConfig['password'] ?? $odooConfig['api_key'] ?? null;

        if (empty($url) || empty($db) || empty($login) || empty($password)) {
            return ApiResponse::error('INVALID_CONNECTOR_CONFIG', 'Odoo connector configuration is incomplete (needs url, db, login, password).', 422);
        }

        $odooService = app(\App\Services\OdooConnectorService::class);
        $authResult = $odooService->authenticate($url, $db, $login, $password);
        if (! $authResult['success']) {
            return ApiResponse::error('ODOO_AUTH_FAILED', 'Failed to authenticate with Odoo: ' . $authResult['error'], 502);
        }

        try {
            $pdfBinary = $odooService->renderReportPdf(
                $url,
                $db,
                $authResult['uid'],
                $password,
                $data['report_name'],
                $data['record_ids']
            );
        } catch (\Exception $e) {
            return ApiResponse::error('ODOO_REPORT_FAILED', 'Failed to render Odoo report: ' . $e->getMessage(), 502);
        }

        $base64 = base64_encode($pdfBinary);

        // Delegate to unifiedPrint request
        $printPayload = array_merge($data, [
            'document_base64' => $base64,
            'type'            => 'pdf',
        ]);
        unset($printPayload['connector_id'], $printPayload['report_name'], $printPayload['record_ids']);

        $printRequest = Request::create('/api/v1/print', 'POST', $printPayload);
        $printRequest->attributes->set('client_app', $app);

        return $this->unifiedPrint($printRequest);
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
            'error_code'   => $job->error_code,
            'retry_count'  => $job->retry_count,
            'created_at'   => $job->created_at?->toISOString(),
            'completed_at' => $job->agent_completed_at?->toISOString(),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/jobs/{job_id}/reprint
    // -------------------------------------------------------------------------

    public function reprintJob(Request $request, string $jobId)
    {
        $app = $this->app($request);
        $job = PrintJob::where('job_id', $jobId)->first();
        if (! $job) {
            return ApiResponse::notFound('JOB_NOT_FOUND', 'Job not found.');
        }

        $orchestrator = new PrintJobOrchestrator();
        try {
            $newJob = $orchestrator->retryJob($job, 'Reprint requested via API');
        } catch (\Exception $e) {
            return ApiResponse::error('REPRINT_FAILED', $e->getMessage(), 500);
        }

        return ApiResponse::success([
            'status'          => 'queued',
            'job_id'          => $newJob->job_id,
            'original_job_id' => $job->job_id,
            'agent'           => $newJob->agent?->name,
            'printer'         => $newJob->printer_name,
            'message'         => 'Job reprint queued successfully.',
        ], 202);
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
            'template'   => 'required|string',
            'data'       => 'nullable|array',
            'parameters' => 'nullable|array',
            'options'    => 'nullable|array',
        ]);

        $template = PrintTemplate::where('name', $data['template'])->first();
        if (! $template) {
            return ApiResponse::notFound('TEMPLATE_NOT_FOUND', 'Template not found.');
        }

        $printData = $template->resolveParameters(
            $data['parameters'] ?? [],
            $data['data'] ?? []
        );

        $engine    = new ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $printData, $data['options'] ?? []);

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
    // Connector Registry  (Phase 2.1)
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/connectors — list connectors for the authenticated client app.
     */
    public function listConnectors(Request $request)
    {
        $app = $this->app($request);

        $connectors = Connector::where('client_app_id', $app->id)
            ->orderBy('name')
            ->get()
            ->map(fn(Connector $c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'type'         => $c->type,
                'config'       => $c->config,
                'icon'         => $c->icon,
                'is_active'    => $c->is_active,
                'last_test_at' => $c->last_test_at?->toIso8601String(),
                'created_at'   => $c->created_at?->toIso8601String(),
                'updated_at'   => $c->updated_at?->toIso8601String(),
            ]);

        return ApiResponse::success(['connectors' => $connectors]);
    }

    /**
     * POST /api/v1/connectors — register a new connector.
     */
    public function registerConnector(Request $request)
    {
        $app = $this->app($request);

        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'type'   => 'required|string|in:api,webhook,odoo,custom',
            'config' => 'required|array',
            'icon'   => 'nullable|string|max:255',
        ]);

        $connector = Connector::create([
            'client_app_id' => $app->id,
            'name'          => $data['name'],
            'type'          => $data['type'],
            'config'        => $data['config'],
            'icon'          => $data['icon'] ?? null,
        ]);

        return ApiResponse::success([
            'connector' => [
                'id'           => $connector->id,
                'name'         => $connector->name,
                'type'         => $connector->type,
                'config'       => $connector->config,
                'icon'         => $connector->icon,
                'is_active'    => $connector->is_active,
                'last_test_at' => $connector->last_test_at?->toIso8601String(),
                'created_at'   => $connector->created_at?->toIso8601String(),
                'updated_at'   => $connector->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/connectors/{id} — update a connector.
     */
    public function updateConnector(Request $request, string $id)
    {
        $app = $this->app($request);

        $connector = Connector::where('id', $id)->where('client_app_id', $app->id)->first();
        if (! $connector) {
            return ApiResponse::notFound('CONNECTOR_NOT_FOUND', 'Connector not found.');
        }

        $data = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'type'   => 'sometimes|required|string|in:api,webhook,odoo,custom',
            'config' => 'sometimes|required|array',
            'icon'   => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $connector->update($data);

        return ApiResponse::success([
            'connector' => [
                'id'           => $connector->id,
                'name'         => $connector->name,
                'type'         => $connector->type,
                'config'       => $connector->config,
                'icon'         => $connector->icon,
                'is_active'    => $connector->is_active,
                'last_test_at' => $connector->last_test_at?->toIso8601String(),
                'created_at'   => $connector->created_at?->toIso8601String(),
                'updated_at'   => $connector->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/connectors/{id} — delete a connector.
     */
    public function deleteConnector(Request $request, string $id)
    {
        $app = $this->app($request);

        $connector = Connector::where('id', $id)->where('client_app_id', $app->id)->first();
        if (! $connector) {
            return ApiResponse::notFound('CONNECTOR_NOT_FOUND', 'Connector not found.');
        }

        $connector->delete();

        return ApiResponse::success([
            'message' => 'Connector deleted successfully.',
        ]);
    }

    /**
     * POST /api/v1/connectors/{id}/test — test a connector connection.
     */
    public function testConnector(Request $request, string $id)
    {
        $app = $this->app($request);

        $connector = Connector::where('id', $id)->where('client_app_id', $app->id)->first();
        if (! $connector) {
            return ApiResponse::notFound('CONNECTOR_NOT_FOUND', 'Connector not found.');
        }

        $result = $connector->testConnection();

        return ApiResponse::success([
            'connector_id' => $connector->id,
            'success'      => $result['success'],
            'message'      => $result['message'],
            'latency_ms'   => $result['latency_ms'],
            'last_test_at' => $connector->fresh()->last_test_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/connectors/{id}/fetch-preview — fetch live preview data
     * from a client app through its configured connector.
     */
    public function fetchPreview(Request $request, string $id)
    {
        $app = $this->app($request);
        $connector = Connector::where('client_app_id', $app->id)->findOrFail($id);

        // Only webhook, api, and odoo connectors support live preview fetching.
        if (! in_array($connector->type, ['webhook', 'api', 'odoo'], true)) {
            return ApiResponse::error('UNSUPPORTED_CONNECTOR_TYPE', 'Preview fetching is only supported for webhook, api, and odoo connectors.', 422);
        }

        $payload = [
            'event'     => 'print_hub.preview_request',
            'connector' => [
                'id'   => $connector->id,
                'name' => $connector->name,
                'type' => $connector->type,
            ],
            'client_app' => [
                'id'   => $app->id,
                'name' => $app->name,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            if ($connector->type === 'odoo') {
                $odooConfig = $connector->config ?? [];
                $url      = $odooConfig['url'] ?? $odooConfig['endpoint_url'] ?? null;
                $db       = $odooConfig['db'] ?? $odooConfig['database'] ?? null;
                $login    = $odooConfig['login'] ?? $odooConfig['username'] ?? null;
                $password = $odooConfig['password'] ?? $odooConfig['api_key'] ?? null;
                $model    = $odooConfig['model'] ?? 'res.partner';
                $fields   = $odooConfig['fields'] ?? [];
                $domain   = $odooConfig['domain'] ?? [];

                $odooService = app(\App\Services\OdooConnectorService::class);
                $auth = $odooService->authenticate($url, $db, $login, $password);
                if (!$auth['success']) {
                    return ApiResponse::error('ODOO_AUTH_FAILED', 'Odoo authentication failed: ' . $auth['error'], 502);
                }

                $records = $odooService->readRecords($url, $db, $auth['uid'], $password, $model, $domain, $fields, 1);
                $data = !empty($records) ? $records[0] : [];
                $receivedAt = now()->toIso8601String();
            } elseif ($connector->type === 'webhook') {
                // Webhook: send POST to the client app via WebhookService.
                $webhookService = app(WebhookService::class);
                $result = $webhookService->sendToConnector($connector, $payload);
                $data = $result['data'] ?? [];
                $receivedAt = $result['received_at'] ?? null;
            } else {
                // API connector: make a direct HTTP GET to the connector's base URL + /preview.
                $url = rtrim($connector->config['base_url'] ?? '', '/') . '/preview';
                $response = Http::timeout(15)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . ($connector->config['api_key'] ?? ''),
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ])
                    ->get($url);

                if ($response->failed()) {
                    return ApiResponse::error(
                        'FETCH_FAILED',
                        'Failed to fetch preview data: HTTP ' . $response->status(),
                        $response->status()
                    );
                }

                $data = $response->json('data', []);
                $receivedAt = now()->toIso8601String();
            }
        } catch (\Exception $e) {
            return ApiResponse::error(
                'FETCH_ERROR',
                'Error fetching preview data: ' . $e->getMessage(),
                500
            );
        }

        return ApiResponse::success([
            'connector_id' => $connector->id,
            'data'         => $data,
            'received_at'  => $receivedAt,
        ]);
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

    // -------------------------------------------------------------------------
    // POST /api/v1/templates/{name}/validate
    // -------------------------------------------------------------------------

    public function validateTemplateData(Request $request, string $name)
    {
        $app = $this->app($request);

        $template = PrintTemplate::where('name', $name)
            ->where('client_app_id', $app->id)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        $data = $request->input('data', []);
        $errors = [];
        $warnings = [];

        // Check required schema fields
        $schema = $template->dataSchema;
        if ($schema) {
            $fieldKeys = $schema->getFieldKeys();
            $providedKeys = array_keys($data);

            // Find missing required fields
            foreach ($fieldKeys as $key) {
                if (!in_array($key, $providedKeys) && !str_contains($key, '.')) {
                    $warnings[] = "Missing recommended field: {$key}";
                }
            }

            // Validate field types if schema has type info
            $structure = $schema->getTableStructure();
            foreach ($structure as $table => $columns) {
                foreach ($columns as $column) {
                    $fieldKey = $table . '.' . $column['name'];
                    if (isset($data[$fieldKey])) {
                        $typeCheck = DataSchema::applyFormat($data[$fieldKey], $column['type'] ?? 'string', null);
                        if ($typeCheck === null && $data[$fieldKey] !== null) {
                            $errors[] = "Field '{$fieldKey}' has invalid type (expected {$column['type']})";
                        }
                    }
                }
            }
        }

        // Check for unknown fields
        $elements = $template->elements ?? [];
        $usedKeys = [];
        array_walk_recursive($elements, function($v, $k) use (&$usedKeys) {
            if ($k === 'key' || $k === 'field_key') $usedKeys[] = $v;
        });

        foreach ($data as $key => $value) {
            if (!in_array($key, $usedKeys) && !str_starts_with($key, '_')) {
                $warnings[] = "Field '{$key}' is provided but not used in the template";
            }
        }

        return response()->json([
            'success' => empty($errors),
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'field_count' => count($data),
            'template_fields' => $usedKeys,
            'matched_fields' => array_intersect($usedKeys, array_keys($data)),
            'missing_fields' => array_diff($usedKeys, array_keys($data)),
        ]);
    }
}
