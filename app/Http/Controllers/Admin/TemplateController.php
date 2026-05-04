<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintTemplate;
use App\Models\PrintJob;
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

    public function preview(Request $request)
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

        $template = new PrintTemplate($data);
        $engine = new ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $data['sample_data'] ?? []);

        return response($pdfBinary)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="preview.pdf"');
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
}
