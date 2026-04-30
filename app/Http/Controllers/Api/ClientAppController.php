<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientApp;
use App\Models\DataSchema;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientAppController extends Controller
{
    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    private function authenticate(Request $request): ?ClientApp
    {
        $key = $request->header('X-API-Key');
        if (! $key) return null;

        $app = ClientApp::where('api_key', $key)->where('is_active', true)->first();
        if ($app) {
            $app->update(['last_used_at' => now()]);
        }
        return $app;
    }

    private function unauthorized()
    {
        return response()->json([
            'error' => 'Unauthorized. Provide a valid X-API-Key header.',
        ], 401);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/test
    // -------------------------------------------------------------------------

    public function testConnection(Request $request)
    {
        $app = $this->authenticate($request);
        if (! $app) return $this->unauthorized();

        $onlineAgentCount = PrintAgent::where('is_active', true)->get()->filter->isOnline()->count();

        return response()->json([
            'success'     => true,
            'message'     => 'Connected successfully.',
            'app_name'    => $app->name,
            'agents'      => $onlineAgentCount,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/agents/online
    // -------------------------------------------------------------------------

    public function getOnlineAgents(Request $request)
    {
        $agents = PrintAgent::where('is_active', true)->get()->filter->isOnline();

        $data = $agents->map(fn($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'printers' => $a->printers ?? [],
        ])->values();

        return response()->json(['agents' => $data]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/queues
    // -------------------------------------------------------------------------

    public function listQueues(Request $request)
    {
        if (! $this->authenticate($request)) return $this->unauthorized();

        $queues = PrintProfile::with('agent:id,name,last_seen_at')
            ->get()
            ->map(fn($p) => [
                'name'        => $p->name,
                'description' => $p->description,
                'printer'     => $p->default_printer,
                'is_online'   => $p->agent ? $p->agent->isOnline() : false,
                'agent_name'  => $p->agent?->name,
            ]);

        return response()->json(['queues' => $queues]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/templates
    // -------------------------------------------------------------------------

    public function listTemplates(Request $request)
    {
        if (! $this->authenticate($request)) return $this->unauthorized();

        $templates = PrintTemplate::all()->map(function ($t) {
            $elements = $t->elements ?? [];
            $fields = collect($elements)
                ->where('type', 'field')
                ->pluck('key')
                ->values();

            $tables = collect($elements)
                ->where('type', 'table')
                ->map(fn($el) => [
                    'key'     => $el['key'],
                    'columns' => collect($el['columns'] ?? [])->map(fn($c) => [
                        'label' => $c['label'],
                        'key'   => $c['key'],
                    ])->values(),
                ])->values();

            return [
                'name'             => $t->name,
                'paper_width_mm'   => $t->paper_width_mm,
                'paper_height_mm'  => $t->paper_height_mm,
                'fields'           => $fields,
                'tables'           => $tables,
                'schema'           => $t->dataSchema ? [
                    'name'    => $t->dataSchema->schema_name,
                    'version' => $t->dataSchema->version,
                ] : null,
            ];
        });

        return response()->json(['templates' => $templates]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/templates/{name}
    // -------------------------------------------------------------------------

    public function getTemplate(Request $request, string $name)
    {
        if (! $this->authenticate($request)) return $this->unauthorized();

        $template = PrintTemplate::where('name', $name)->first();
        if (! $template) {
            return response()->json(['error' => "Template '{$name}' not found."], 404);
        }

        $elements = $template->elements ?? [];
        $fields = collect($elements)
            ->where('type', 'field')
            ->map(fn($el) => [
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

        $tables = collect($elements)
            ->where('type', 'table')
            ->map(fn($el) => [
                'key'     => $el['key'],
                'x'       => $el['x'],
                'y'       => $el['y'],
                'columns' => collect($el['columns'] ?? [])->map(fn($c) => [
                    'label' => $c['label'],
                    'key'   => $c['key'],
                    'width' => $c['width'],
                ])->values(),
            ])->values();

        return response()->json([
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
    // GET /api/v1/templates/{name}/schema  — Bidirectional Schema Discovery
    // -------------------------------------------------------------------------

    public function getTemplateSchema(Request $request, string $name)
    {
        if (! $this->authenticate($request)) return $this->unauthorized();

        $template = PrintTemplate::with('dataSchema')->where('name', $name)->first();
        if (! $template) {
            return response()->json(['error' => "Template '{$name}' not found."], 404);
        }

        return response()->json($template->buildRequiredSchema());
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/schema  —  Register or update a data schema (versioned)
    // -------------------------------------------------------------------------

    public function registerSchema(Request $request)
    {
        $app = $this->authenticate($request);
        if (! $app) return $this->unauthorized();

        $data = $request->validate([
            'schema_name' => 'required|string|max:100',
            'label'       => 'nullable|string|max:255',
            'fields'      => 'nullable|array',
            'tables'      => 'nullable|array',
            'sample_data' => 'nullable|array',
        ]);

        $schemaName = $data['schema_name'];
        $existing = DataSchema::forSchema($schemaName)->latest()->first();

        // Check if content actually changed
        $hasChanges = true;
        if ($existing) {
            $hasChanges = (
                ($existing->fields ?? []) != ($data['fields'] ?? []) ||
                ($existing->tables ?? []) != ($data['tables'] ?? [])
            );
        }

        if ($hasChanges || !$existing) {
            // Create new version
            $schema = DataSchema::createNewVersion($schemaName, [
                'client_app_id' => $app->id,
                'label'         => $data['label'] ?? $data['schema_name'],
                'fields'        => $data['fields'] ?? [],
                'tables'        => $data['tables'] ?? [],
                'sample_data'   => $data['sample_data'] ?? null,
            ]);

            return response()->json([
                'status'      => 'ok',
                'schema_name' => $schema->schema_name,
                'version'     => $schema->version,
                'is_new'      => true,
                'message'     => "Schema v{$schema->version} created.",
            ]);
        } else {
            // No structural changes — just update sample_data if provided
            if (isset($data['sample_data'])) {
                $existing->update(['sample_data' => $data['sample_data']]);
            }

            return response()->json([
                'status'      => 'ok',
                'schema_name' => $existing->schema_name,
                'version'     => $existing->version,
                'is_new'      => false,
                'message'     => "No structural changes. Schema remains at v{$existing->version}.",
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/schemas
    // -------------------------------------------------------------------------

    public function listSchemas(Request $request)
    {
        if (! $this->authenticate($request)) return $this->unauthorized();

        // By default return only latest versions
        $onlyLatest = $request->query('latest', 'true') !== 'false';

        $query = DataSchema::with('clientApp:id,name');
        if ($onlyLatest) {
            $query->latest();
        }

        $schemas = $query->orderBy('schema_name')->orderByDesc('version')->get()->map(fn($s) => [
            'id'          => $s->id,
            'schema_name' => $s->schema_name,
            'version'     => $s->version,
            'is_latest'   => $s->is_latest,
            'label'       => $s->label,
            'client_app'  => $s->clientApp?->name,
            'fields'      => $s->fields,
            'tables'      => $s->tables,
            'has_sample'  => !empty($s->sample_data),
            'changelog'   => $s->changelog,
            'updated_at'  => $s->updated_at?->toISOString(),
        ]);

        return response()->json(['schemas' => $schemas]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/schema/{name}/versions — Schema version history
    // -------------------------------------------------------------------------

    public function schemaVersions(Request $request, string $name)
    {
        if (! $this->authenticate($request)) return $this->unauthorized();

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
            return response()->json(['error' => "Schema '{$name}' not found."], 404);
        }

        return response()->json([
            'schema_name' => $name,
            'versions'    => $versions,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/print  (unified endpoint)
    // -------------------------------------------------------------------------

    public function unifiedPrint(Request $request)
    {
        $app = $this->authenticate($request);
        if (! $app) return $this->unauthorized();

        $data = $request->validate([
            'template'        => 'nullable|string',
            'data'            => 'nullable|array',
            'document_base64' => 'nullable|string',
            'type'            => 'nullable|string',
            'agent_id'        => 'nullable|integer|exists:print_agents,id',
            'printer'         => 'nullable|string',
            'profile'         => 'nullable|string',
            'queue'           => 'nullable|string',
            'reference_id'    => 'nullable|string',
            'webhook_url'     => 'nullable|url',
            'options'         => 'nullable|array',
            'skip_validation' => 'nullable|boolean',
            'branch_code'     => 'nullable|string',
            'branch_id'       => 'nullable|integer',
        ]);

        // Must have either template or document
        if (empty($data['template']) && empty($data['document_base64'])) {
            return response()->json([
                'error' => 'Provide either "template" (with "data") or "document_base64".',
            ], 422);
        }

        // 0. Resolve Branch (if provided)
        $branch = null;
        $branchId = null;
        if (!empty($data['branch_code'])) {
            $branch = \App\Models\Branch::where('code', $data['branch_code'])->first();
            if (!$branch) {
                return response()->json(['error' => "Branch '{$data['branch_code']}' not found."], 404);
            }
            $branchId = $branch->id;
        } elseif (!empty($data['branch_id'])) {
            $branch = \App\Models\Branch::find($data['branch_id']);
            if (!$branch) {
                return response()->json(['error' => "Branch ID {$data['branch_id']} not found."], 404);
            }
            $branchId = $branch->id;
        }

        // 1. Resolve Profile / Queue settings
        $profile = null;
        $profileName = $data['queue'] ?? $data['profile'] ?? null;

        // Try explicit queue name first
        if ($profileName) {
            $profile = \App\Models\PrintProfile::with('agent')->where('name', $profileName)->first();
        }

        // If no explicit queue but branch + template → use branch template default
        if (!$profile && $branch && !empty($data['template'])) {
            $template = PrintTemplate::where('name', $data['template'])->first();
            if ($template) {
                $defaultProfile = $branch->getDefaultProfileForTemplate($template->id);
                if ($defaultProfile) {
                    $profile = $defaultProfile;
                    $profileName = $profile->name;
                }
            }
        }

        // 2. Resolve Options (merge Profile -> Request)
        $options = $data['options'] ?? [];
        if ($profile) {
            $profileOpts = [
                'orientation' => $profile->orientation,
                'copies'      => $profile->copies,
                'duplex'      => $profile->duplex,
                'margin_top'    => $profile->margin_top,
                'margin_bottom' => $profile->margin_bottom,
                'margin_left'   => $profile->margin_left,
                'margin_right'  => $profile->margin_right,
                'fit_to_page'   => $profile->extra_options['fit_to_page'] ?? false,
            ];
            
            // Map paper size
            if ($profile->is_custom) {
                $profileOpts['paper_width_mm']  = $profile->custom_width;
                $profileOpts['paper_height_mm'] = $profile->custom_height;
            } else {
                $profileOpts['paper_size'] = $profile->paper_size;
                
                // Map known sizes to mm for the Engine
                $sizes = [
                    'A4'           => [210, 297],
                    'A5'           => [148, 210],
                    'Letter'       => [215.9, 279.4],
                    'Half Letter'  => [139.7, 215.9],
                    'Legal'        => [215.9, 355.6],
                    'F4'           => [210, 330],
                    'Statement'    => [139.7, 215.9],
                    'Executive'    => [184.1, 266.7],
                    'Envelope #10' => [104.8, 241.3],
                ];
                if (isset($sizes[$profile->paper_size])) {
                    $profileOpts['paper_width_mm']  = $sizes[$profile->paper_size][0];
                    $profileOpts['paper_height_mm'] = $sizes[$profile->paper_size][1];
                }
            }

            $options = array_merge($profileOpts, $options);
        }

        // 3. Auto-select agent
        // Priority: explicit agent_id → profile's pinned agent → any online agent in branch → any online agent
        $agent = null;
        
        if (! empty($data['agent_id'])) {
            $agent = PrintAgent::where('id', $data['agent_id'])->where('is_active', true)->first();
        } elseif ($profile && $profile->print_agent_id) {
            $agent = $profile->agent;
            if ($agent && !$agent->isOnline()) {
                return response()->json(['error' => "The Hub assigned to queue '{$profileName}' is offline."], 503);
            }
        } elseif ($branchId) {
            // Find any online agent in the branch
            $agent = PrintAgent::where('is_active', true)
                ->where('branch_id', $branchId)
                ->get()
                ->first(fn($a) => $a->isOnline());
        }

        // Fallback: any online agent globally
        if (! $agent) {
            $agent = PrintAgent::where('is_active', true)->get()->first(fn($a) => $a->isOnline());
        }

        if (! $agent) {
            return response()->json(['error' => 'No online agent available.'], 503);
        }

        // 4. Auto-select printer (Priority: Request > Profile > Default)
        $printer = $data['printer'] ?? null;
        if (!$printer && $profile) {
            $printer = $profile->default_printer;
        }
        if (! $printer) {
            $p = \App\Models\PrintProfile::first();
            $printer = $p?->default_printer ?? 'Default';
        }

        // Prepare Job Metadata
        $jobId    = (string) Str::uuid();
        $type     = $data['type'] ?? 'pdf';
        $extension = ($type === 'pdf') ? 'pdf' : 'raw';
        $filePath = "print_jobs/{$jobId}.{$extension}";
        $templateName = null;
        $validationWarnings = [];

        if (! empty($data['template'])) {
            $template = PrintTemplate::with('dataSchema')->where('name', $data['template'])->first();
            if (! $template) {
                return response()->json(['error' => "Template '{$data['template']}' not found."], 404);
            }
            $templateName = $template->name;

            // Schema validation
            $printData = $data['data'] ?? [];
            if ($template->dataSchema && !($data['skip_validation'] ?? false)) {
                $errors = $template->dataSchema->validateData($printData);
                if (!empty($errors)) {
                    $validationWarnings = $errors;
                }
            }

            $engine = new \App\Services\ContinuousFormEngine();
            $pdfBinary = $engine->generate($template, $printData, $options);
            Storage::put($filePath, $pdfBinary);
        } else {
            $base64Data = $data['document_base64'];
            $base64Data = preg_replace('/\s+/', '', $base64Data);

            if (strlen($base64Data) % 4 === 1) {
                return response()->json(['error' => 'Invalid base64 string length.'], 422);
            }

            $decoded = base64_decode($base64Data, true);
            if ($decoded === false) {
                return response()->json(['error' => 'Invalid base64-encoded content.'], 422);
            }
            Storage::put($filePath, $decoded);
        }

        // Create job
        $job = PrintJob::create([
            'job_id'          => $jobId,
            'print_agent_id'  => $agent->id,
            'branch_id'       => $branchId,
            'printer_name'    => $printer,
            'type'            => $type,
            'status'          => 'pending',
            'file_path'       => $filePath,
            'webhook_url'     => $data['webhook_url'] ?? null,
            'reference_id'    => $data['reference_id'] ?? null,
            'options'         => $options,
            'template_data'   => !empty($data['template']) ? ($data['data'] ?? null) : null,
            'template_name'   => $templateName,
        ]);

        $response = [
            'status'    => 'queued',
            'job_id'    => $jobId,
            'agent'     => $agent->name,
            'printer'   => $printer,
            'template'  => $templateName,
            'queue'     => $profile ? $profile->name : null,
        ];

        if (!empty($validationWarnings)) {
            $response['warnings'] = $validationWarnings;
        }

        return response()->json($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/jobs  (legacy submit — kept for backwards compat)
    // -------------------------------------------------------------------------

    public function submitJob(Request $request)
    {
        $app = $this->authenticate($request);
        if (! $app) return $this->unauthorized();

        $data = $request->validate([
            'agent_id'        => 'required|exists:print_agents,id',
            'printer'         => 'required|string',
            'type'            => 'nullable|string',
            'document_base64' => 'nullable|string',
            'template'        => 'nullable|string|exists:print_templates,name',
            'template_data'   => 'nullable|array',
            'webhook_url'     => 'nullable|url',
            'reference_id'    => 'nullable|string',
            'options'         => 'nullable|array',
        ]);

        $jobId    = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.pdf";
        $type     = $data['type'] ?? 'pdf';

        if ($request->filled('template')) {
            $template  = PrintTemplate::where('name', $data['template'])->first();
            $engine    = new \App\Services\ContinuousFormEngine();
            $pdfBinary = $engine->generate($template, $data['template_data'] ?? []);
            Storage::put($filePath, $pdfBinary);
            $type = 'pdf';
        } elseif ($request->filled('document_base64')) {
            $base64Data = preg_replace('/\s+/', '', $data['document_base64']);
            if (strlen($base64Data) % 4 === 1) {
                return response()->json(['error' => 'Invalid base64 string length.'], 422);
            }
            $decoded = base64_decode($base64Data, true);
            if ($decoded === false) {
                return response()->json(['error' => 'Invalid base64-encoded PDF.'], 422);
            }
            Storage::put($filePath, $decoded);
        } else {
            return response()->json(['error' => 'Either template or document_base64 is required.'], 400);
        }

        $job = PrintJob::create([
            'job_id'         => $jobId,
            'print_agent_id' => $data['agent_id'],
            'printer_name'   => $data['printer'],
            'type'           => $type,
            'status'         => 'pending',
            'file_path'      => $filePath,
            'webhook_url'    => $data['webhook_url'] ?? null,
            'reference_id'   => $data['reference_id'] ?? null,
            'options'        => $data['options'] ?? null,
            'template_data'  => $request->filled('template') ? ($data['template_data'] ?? null) : null,
            'template_name'  => $data['template'] ?? null,
        ]);

        return response()->json([
            'status'  => 'queued',
            'job_id'  => $jobId,
            'message' => 'Job successfully queued.',
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/jobs/{job_id}
    // -------------------------------------------------------------------------

    public function jobStatus(Request $request, string $jobId)
    {
        if (! $this->authenticate($request)) return $this->unauthorized();

        $job = PrintJob::where('job_id', $jobId)->first();
        if (! $job) {
            return response()->json(['error' => 'Job not found.'], 404);
        }

        return response()->json([
            'job_id'       => $job->job_id,
            'status'       => $job->status,
            'reference_id' => $job->reference_id,
            'printer'      => $job->printer_name,
            'error'        => $job->error,
            'created_at'   => $job->created_at?->toISOString(),
            'completed_at' => $job->agent_completed_at?->toISOString(),
        ]);
    // -------------------------------------------------------------------------
    // POST /api/v1/preview  — Generate a PDF without queuing
    // -------------------------------------------------------------------------

    public function previewPrint(Request $request)
    {
        $app = $this->authenticate($request);
        if (! $app) return $this->unauthorized();

        $data = $request->validate([
            'template'        => 'required|string',
            'data'            => 'nullable|array',
            'options'         => 'nullable|array',
        ]);

        $template = PrintTemplate::where('name', $data['template'])->first();
        if (! $template) {
            return response()->json(['error' => "Template not found."], 404);
        }

        $engine = new \App\Services\ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $data['data'] ?? [], $data['options'] ?? []);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"'
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/print/batch  — Queue multiple jobs
    // -------------------------------------------------------------------------

    public function batchPrint(Request $request)
    {
        $app = $this->authenticate($request);
        if (! $app) return $this->unauthorized();

        $jobs = $request->validate([
            'jobs' => 'required|array',
            'jobs.*.template'        => 'nullable|string',
            'jobs.*.data'            => 'nullable|array',
            'jobs.*.document_base64' => 'nullable|string',
            'jobs.*.printer'         => 'nullable|string',
            'jobs.*.queue'           => 'nullable|string',
            'jobs.*.reference_id'    => 'nullable|string',
        ]);

        $results = [];
        foreach ($jobs['jobs'] as $jobData) {
            $req = $request->duplicate(null, $jobData);
            $res = $this->unifiedPrint($req);
            $results[] = json_decode($res->getContent(), true);
        }

        return response()->json(['batch_results' => $results]);
    }
}
