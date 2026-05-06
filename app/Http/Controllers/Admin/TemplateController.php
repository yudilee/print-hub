<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintTemplate;
use App\Models\PrintJob;
use App\Models\TemplateVersion;
use App\Services\ContinuousFormEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = PrintTemplate::orderBy('name')->get();
        return view('admin.templates.index', compact('templates'));
    }

    public function create()
    {
        $schemas = \App\Models\DataSchema::where('is_latest', true)->orderBy('schema_name')->get();
        return view('admin.templates.designer', ['template' => new PrintTemplate(), 'schemas' => $schemas]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|unique:print_templates,name',
            'data_schema_id'        => 'nullable|exists:data_schemas,id',
            'data_schema_version'   => 'nullable|integer',
            'paper_width_mm'        => 'required|numeric',
            'paper_height_mm'       => 'required|numeric',
            'elements'              => 'nullable|array',
            'styles'                => 'nullable|array',
            'background_config'     => 'nullable|array',
            'background_image_path' => 'nullable|string',
        ]);

        if (!empty($data['data_schema_id']) && empty($data['data_schema_version'])) {
            $schema = \App\Models\DataSchema::find($data['data_schema_id']);
            if ($schema) $data['data_schema_version'] = $schema->version;
        }

        PrintTemplate::create($data);

        return response()->json(['status' => 'ok']);
    }

    public function edit(PrintTemplate $template)
    {
        $schemas = \App\Models\DataSchema::where('is_latest', true)->orderBy('schema_name')->get();
        $template->load('dataSchema');
        return view('admin.templates.designer', compact('template', 'schemas'));
    }

    public function update(Request $request, PrintTemplate $template)
    {
        $data = $request->validate([
            'name'                  => 'required|unique:print_templates,name,' . $template->id,
            'data_schema_id'        => 'nullable|exists:data_schemas,id',
            'data_schema_version'   => 'nullable|integer',
            'paper_width_mm'        => 'required|numeric',
            'paper_height_mm'       => 'required|numeric',
            'elements'              => 'nullable|array',
            'styles'                => 'nullable|array',
            'background_config'     => 'nullable|array',
            'background_image_path' => 'nullable|string',
        ]);

        if (!empty($data['data_schema_id']) && empty($data['data_schema_version'])) {
            $schema = \App\Models\DataSchema::find($data['data_schema_id']);
            if ($schema) $data['data_schema_version'] = $schema->version;
        }

        // Auto-save snapshot before updating
        $template->createSnapshot('Auto-save', 'Auto-saved before update', $request->user());

        $template->update($data);

        return response()->json(['status' => 'ok']);
    }

    public function destroy(PrintTemplate $template)
    {
        $template->delete();
        return redirect()->route('admin.templates')->with('success', 'Template deleted.');
    }

    public function clone(PrintTemplate $template)
    {
        $clone = $template->replicate();
        $clone->name = $template->name . ' (Copy)';
        $clone->save();

        return redirect()->route('admin.templates.edit', $clone)
            ->with('success', 'Template cloned successfully.');
    }

    public function jobHistory(PrintTemplate $template)
    {
        $jobs = PrintJob::where('template_name', $template->name)
            ->whereNotNull('template_data')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json(['jobs' => $jobs]);
    }

    public function uploadBg(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048'
        ]);

        $path = $request->file('image')->store('template_bg', 'public');

        return response()->json([
            'status' => 'ok',
            'url'    => '/storage/' . $path
        ]);
    }

    public function preview(Request $request, ?PrintTemplate $template = null)
    {
        $data = $request->validate([
            'paper_width_mm'        => 'required|numeric',
            'paper_height_mm'       => 'required|numeric',
            'elements'              => 'nullable|array',
            'styles'                => 'nullable|array',
            'background_config'     => 'nullable|array',
            'background_image_path' => 'nullable|string',
            'sample_data'           => 'nullable|array',
        ]);

        $templateModel = new PrintTemplate($data);
        $engine = new ContinuousFormEngine();
        $pdfBinary = $engine->generate($templateModel, $data['sample_data'] ?? []);

        // Estimate page count using PDF header/metadata
        // Count pages by searching for /Type /Page in PDF content
        $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/i', $pdfBinary);

        return response($pdfBinary)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="preview.pdf"')
            ->header('X-Page-Count', (string) max(1, $pageCount));
    }

    /**
     * Save sample data for a template via AJAX.
     */
    public function saveSampleData(Request $request, PrintTemplate $template)
    {
        $data = $request->validate([
            'sample_data' => 'nullable|array',
        ]);

        $template->update([
            'sample_data' => $data['sample_data'] ?? [],
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Get sample data for a template via AJAX.
     */
    public function getSampleData(PrintTemplate $template)
    {
        return response()->json([
            'sample_data' => $template->sample_data ?? [],
        ]);
    }

    public function testPrint(Request $request)
    {
        $data = $request->validate([
            'template_data' => 'required|array',
            'sample_data'   => 'nullable|array',
            'agent_id'      => 'required|exists:print_agents,id',
            'printer_name'  => 'required|string',
        ]);

        $template = new PrintTemplate($data['template_data']);
        $engine = new ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $data['sample_data'] ?? []);

        $jobId = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.pdf";
        \Illuminate\Support\Facades\Storage::put($filePath, $pdfBinary);

        PrintJob::create([
            'job_id'         => $jobId,
            'print_agent_id' => $data['agent_id'],
            'printer_name'   => $data['printer_name'],
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => $filePath,
        ]);

        return response()->json(['status' => 'ok', 'job_id' => $jobId]);
    }

    // ── Version History ──────────────────────────────────────

    /**
     * Show version history for a template.
     */
    public function versions(PrintTemplate $template)
    {
        $versions = $template->versions()->with('creator')->orderBy('version_number', 'desc')->paginate(20);
        return view('admin.templates.versions', compact('template', 'versions'));
    }

    /**
     * Create a manual version snapshot.
     */
    public function createVersion(Request $request, PrintTemplate $template)
    {
        $validated = $request->validate([
            'label'     => 'nullable|string|max:255',
            'changelog' => 'nullable|string|max:1000',
        ]);

        $version = $template->createSnapshot(
            $validated['label'] ?? null,
            $validated['changelog'] ?? null,
            $request->user()
        );

        return redirect()->route('templates.versions', $template)
            ->with('success', "Version {$version->version_number} created.");
    }

    /**
     * Restore a template to a previous version.
     */
    public function restoreVersion(Request $request, PrintTemplate $template, TemplateVersion $version)
    {
        // Make sure the version belongs to this template
        abort_if($version->print_template_id !== $template->id, 404);

        $template->restoreVersion($version);

        return redirect()->route('admin.templates.edit', $template)
            ->with('success', "Restored to version {$version->version_number}.");
    }

    /**
     * Compare two versions and show diff.
     */
    public function diffVersions(Request $request, PrintTemplate $template, TemplateVersion $v1, TemplateVersion $v2)
    {
        abort_if($v1->print_template_id !== $template->id || $v2->print_template_id !== $template->id, 404);

        // Compare elements between two versions
        $diff = $this->computeElementDiff($v1->elements ?? [], $v2->elements ?? []);

        if ($request->wantsJson()) {
            return response()->json($diff);
        }

        return view('admin.templates.diff', compact('template', 'v1', 'v2', 'diff'));
    }

    /**
     * Flatten elements from sections-based format to a flat array,
     * supporting both legacy and new formats.
     */
    private static function flattenElements(array $elements): array
    {
        // Legacy flat format: numeric array of element objects
        if (array_is_list($elements)) {
            return $elements;
        }

        // Sections format: { sections: {...}, elements: [...] }
        if (isset($elements['sections']) && isset($elements['elements'])) {
            $flat = [];
            $sectionOrder = ['pageHeader', 'reportHeader', 'detail', 'reportFooter', 'pageFooter'];
            foreach ($sectionOrder as $key) {
                $sec = $elements['sections'][$key] ?? [];
                if (!empty($sec['elements']) && is_array($sec['elements'])) {
                    array_push($flat, ...$sec['elements']);
                }
            }
            return $flat;
        }

        // Fallback: return as-is
        return $elements;
    }

    /**
     * Compute a structured diff between two element arrays.
     */
    private function computeElementDiff(array $oldElements, array $newElements): array
    {
        $diff = ['added' => [], 'removed' => [], 'modified' => [], 'unchanged' => []];

        // Flatten both to support sections-based format
        $oldElements = self::flattenElements($oldElements);
        $newElements = self::flattenElements($newElements);

        // Index by element ID, falling back to type+x+y for elements without IDs
        $oldIndex = [];
        foreach ($oldElements as $el) {
            $key = $el['id'] ?? ($el['type'] . '_' . ($el['x'] ?? 0) . '_' . ($el['y'] ?? 0));
            $oldIndex[$key] = $el;
        }

        $newIndex = [];
        foreach ($newElements as $el) {
            $key = $el['id'] ?? ($el['type'] . '_' . ($el['x'] ?? 0) . '_' . ($el['y'] ?? 0));
            $newIndex[$key] = $el;
        }

        foreach ($newIndex as $key => $el) {
            if (!isset($oldIndex[$key])) {
                $diff['added'][] = $el;
            } elseif (json_encode($el) !== json_encode($oldIndex[$key])) {
                $diff['modified'][] = ['old' => $oldIndex[$key], 'new' => $el];
            } else {
                $diff['unchanged'][] = $el;
            }
        }

        foreach ($oldIndex as $key => $el) {
            if (!isset($newIndex[$key])) {
                $diff['removed'][] = $el;
            }
        }

        return $diff;
    }
}
