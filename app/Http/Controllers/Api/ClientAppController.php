<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientApp;
use App\Models\PrintAgent;
use App\Models\PrintJob;
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
    // GET /api/v1/agents/online  (unchanged, no auth required for discovery)
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
            'agent_id'        => 'nullable|integer|exists:print_agents,id',
            'printer'         => 'nullable|string',
            'reference_id'    => 'nullable|string',
            'webhook_url'     => 'nullable|url',
            'options'         => 'nullable|array',
        ]);

        // Must have either template or document
        if (empty($data['template']) && empty($data['document_base64'])) {
            return response()->json([
                'error' => 'Provide either "template" (with "data") or "document_base64".',
            ], 422);
        }

        // Auto-select agent if not specified
        $agent = null;
        if (! empty($data['agent_id'])) {
            $agent = PrintAgent::where('id', $data['agent_id'])->where('is_active', true)->first();
        } else {
            // Pick first online agent
            $agent = PrintAgent::where('is_active', true)->get()->first(fn($a) => $a->isOnline());
        }

        if (! $agent) {
            return response()->json(['error' => 'No online agent available.'], 503);
        }

        // Auto-select printer if not specified
        $printer = $data['printer'] ?? null;
        if (! $printer) {
            $profiles = \App\Models\PrintProfile::all();
            $printer = $profiles->first()?->default_printer ?? 'Default';
        }

        // Build PDF
        $jobId    = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.pdf";
        $type     = 'pdf';
        $templateName = null;

        if (! empty($data['template'])) {
            $template = PrintTemplate::where('name', $data['template'])->first();
            if (! $template) {
                return response()->json(['error' => "Template '{$data['template']}' not found."], 404);
            }
            $templateName = $template->name;
            $engine = new \App\Services\ContinuousFormEngine();
            $pdfBinary = $engine->generate($template, $data['data'] ?? []);
            Storage::put($filePath, $pdfBinary);
        } else {
            $base64Data = $data['document_base64'];
            // Clean up whitespace/newlines
            $base64Data = preg_replace('/\s+/', '', $base64Data);
            
            // Validate length (must not be n % 4 == 1)
            if (strlen($base64Data) % 4 === 1) {
                return response()->json(['error' => 'Invalid base64 string length. Truncated payload?'], 422);
            }

            $decoded = base64_decode($base64Data, true);
            if ($decoded === false) {
                return response()->json(['error' => 'Invalid base64-encoded PDF.'], 422);
            }
            Storage::put($filePath, $decoded);
        }

        // Create job
        $job = PrintJob::create([
            'job_id'          => $jobId,
            'print_agent_id'  => $agent->id,
            'printer_name'    => $printer,
            'type'            => $type,
            'status'          => 'pending',
            'file_path'       => $filePath,
            'webhook_url'     => $data['webhook_url'] ?? null,
            'reference_id'    => $data['reference_id'] ?? null,
            'options'         => $data['options'] ?? null,
        ]);

        return response()->json([
            'status'    => 'queued',
            'job_id'    => $jobId,
            'agent'     => $agent->name,
            'printer'   => $printer,
            'template'  => $templateName,
        ]);
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
    }
}
