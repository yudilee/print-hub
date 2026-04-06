<?php

namespace App\Http\Controllers;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Models\PrintJob;
use App\Models\PrintTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // ── Dashboard ──

    public function dashboard()
    {
        $agents = PrintAgent::withCount('jobs')->get();
        $profiles = PrintProfile::all();
        $recentJobs = PrintJob::with('agent')->latest()->take(30)->get();

        $stats = [
            'total_agents' => $agents->count(),
            'online_agents' => $agents->filter(fn($a) => $a->isOnline())->count(),
            'total_profiles' => $profiles->count(),
            'total_jobs' => PrintJob::count(),
            'failed_jobs' => PrintJob::where('status', 'failed')->count(),
        ];

        return view('admin.dashboard', compact('agents', 'profiles', 'recentJobs', 'stats'));
    }

    // ── Agents CRUD ──

    public function agentsIndex()
    {
        $agents = PrintAgent::withCount('jobs')->latest()->get();
        return view('admin.agents', compact('agents'));
    }

    public function agentStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        PrintAgent::create([
            'name' => $data['name'],
            'agent_key' => Str::random(32),
        ]);

        return redirect()->route('admin.agents')->with('success', 'Agent created!');
    }

    public function agentDestroy(PrintAgent $agent)
    {
        $agent->delete();
        return redirect()->route('admin.agents')->with('success', 'Agent removed.');
    }

    // ── Profiles CRUD ──

    public function profilesIndex()
    {
        $profiles = PrintProfile::latest()->get();
        return view('admin.profiles', compact('profiles'));
    }

    public function profileStore(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255|unique:print_profiles,name',
            'description'     => 'nullable|string|max:255',
            'paper_size'      => 'required|string',
            'is_custom'       => 'nullable|boolean',
            'custom_width'    => 'nullable|numeric|required_if:is_custom,1',
            'custom_height'   => 'nullable|numeric|required_if:is_custom,1',
            'margin_top'      => 'nullable|numeric',
            'margin_bottom'   => 'nullable|numeric',
            'margin_left'     => 'nullable|numeric',
            'margin_right'    => 'nullable|numeric',
            'orientation'     => 'required|string',
            'copies'          => 'required|integer|min:1',
            'duplex'          => 'required|string',
            'default_printer' => 'nullable|string|max:255',
        ]);

        $data['is_custom'] = $request->has('is_custom');

        PrintProfile::create($data);
        return redirect()->route('admin.profiles')->with('success', 'Profile created!');
    }

    public function profileDestroy(PrintProfile $profile)
    {
        $profile->delete();
        return redirect()->route('admin.profiles')->with('success', 'Profile removed.');
    }

    // ── Templates CRUD ──

    public function templatesIndex()
    {
        $templates = \App\Models\PrintTemplate::orderBy('name')->get();
        return view('admin.templates.index', compact('templates'));
    }

    public function templateCreate()
    {
        $schemas = \App\Models\DataSchema::where('is_latest', true)->orderBy('schema_name')->get();
        return view('admin.templates.designer', ['template' => new \App\Models\PrintTemplate(), 'schemas' => $schemas]);
    }

    public function templateStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:print_templates,name',
            'data_schema_id' => 'nullable|exists:data_schemas,id',
            'data_schema_version' => 'nullable|integer',
            'paper_width_mm' => 'required|numeric',
            'paper_height_mm' => 'required|numeric',
            'elements' => 'nullable|array',
            'styles' => 'nullable|array',
            'background_config' => 'nullable|array',
            'background_image_path' => 'nullable|string'
        ]);

        // Auto-set schema version from the selected schema
        if (!empty($data['data_schema_id']) && empty($data['data_schema_version'])) {
            $schema = \App\Models\DataSchema::find($data['data_schema_id']);
            if ($schema) $data['data_schema_version'] = $schema->version;
        }

        \App\Models\PrintTemplate::create($data);

        return response()->json(['status' => 'ok']);
    }

    public function templateEdit(\App\Models\PrintTemplate $template)
    {
        $schemas = \App\Models\DataSchema::where('is_latest', true)->orderBy('schema_name')->get();
        $template->load('dataSchema');
        return view('admin.templates.designer', compact('template', 'schemas'));
    }

    public function templateUpdate(Request $request, \App\Models\PrintTemplate $template)
    {
        $data = $request->validate([
            'name' => 'required|unique:print_templates,name,' . $template->id,
            'data_schema_id' => 'nullable|exists:data_schemas,id',
            'data_schema_version' => 'nullable|integer',
            'paper_width_mm' => 'required|numeric',
            'paper_height_mm' => 'required|numeric',
            'elements' => 'nullable|array',
            'styles' => 'nullable|array',
            'background_config' => 'nullable|array',
            'background_image_path' => 'nullable|string'
        ]);

        // Auto-set schema version from the selected schema
        if (!empty($data['data_schema_id']) && empty($data['data_schema_version'])) {
            $schema = \App\Models\DataSchema::find($data['data_schema_id']);
            if ($schema) $data['data_schema_version'] = $schema->version;
        }

        $template->update($data);

        return response()->json(['status' => 'ok']);
    }

    public function templateDestroy(\App\Models\PrintTemplate $template)
    {
        $template->delete();
        return redirect()->route('admin.templates')->with('success', 'Template deleted.');
    }

    public function templateJobHistory(\App\Models\PrintTemplate $template)
    {
        $jobs = \App\Models\PrintJob::where('template_name', $template->name)
            ->whereNotNull('template_data')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
            
        return response()->json(['jobs' => $jobs]);
    }

    public function templateUploadBg(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048'
        ]);

        $path = $request->file('image')->store('template_bg', 'public');

        return response()->json([
            'status' => 'ok',
            'url' => '/storage/' . $path
        ]);
    }

    public function templatePreview(Request $request)
    {
        $data = $request->validate([
            'paper_width_mm' => 'required|numeric',
            'paper_height_mm' => 'required|numeric',
            'elements' => 'nullable|array',
            'styles' => 'nullable|array',
            'background_config' => 'nullable|array',
            'background_image_path' => 'nullable|string',
            'sample_data' => 'nullable|array'
        ]);

        $template = new \App\Models\PrintTemplate($data);
        $engine = new \App\Services\ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $data['sample_data'] ?? []);

        return response($pdfBinary)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="preview.pdf"');
    }

    public function templateTestPrint(Request $request)
    {
        $data = $request->validate([
            'template_data' => 'required|array',
            'sample_data' => 'nullable|array',
            'agent_id' => 'required|exists:print_agents,id',
            'printer_name' => 'required|string'
        ]);

        $tplData = $data['template_data'];
        $template = new \App\Models\PrintTemplate($tplData);
        
        $engine = new \App\Services\ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $data['sample_data'] ?? []);

        $jobId = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.pdf";
        \Illuminate\Support\Facades\Storage::put($filePath, $pdfBinary);

        $job = PrintJob::create([
            'job_id' => $jobId,
            'print_agent_id' => $data['agent_id'],
            'printer_name' => $data['printer_name'],
            'type' => 'pdf',
            'status' => 'pending',
            'file_path' => $filePath,
        ]);

        return response()->json(['status' => 'ok', 'job_id' => $jobId]);
    }

    public function templateClone(PrintTemplate $template)
    {
        $clone = $template->replicate();
        $clone->name = $template->name . ' (Copy)';
        $clone->save();

        return redirect()->route('admin.templates.edit', $clone)
            ->with('success', 'Template cloned successfully.');
    }

    // ── Client Apps CRUD ──

    public function clientsIndex()
    {
        $clients = ClientApp::latest()->get();
        return view('admin.clients', compact('clients'));
    }

    public function clientStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        ClientApp::create([
            'name'    => $data['name'],
            'api_key' => (string) Str::uuid(),
        ]);

        return redirect()->route('admin.clients')->with('success', 'Client app registered!');
    }

    public function clientDestroy(ClientApp $client)
    {
        $client->delete();
        return redirect()->route('admin.clients')->with('success', 'Client app removed.');
    }

    // ── Jobs ──

    public function jobsIndex(Request $request)
    {
        $query = PrintJob::with('agent')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('agent_id')) {
            $query->where('print_agent_id', $request->agent_id);
        }

        $jobs = $query->paginate(50);
        $agents = PrintAgent::all();
        return view('admin.jobs', compact('jobs', 'agents'));
    }

    public function downloadDocument(PrintJob $job)
    {
        if (!$job->file_path || !\Illuminate\Support\Facades\Storage::exists($job->file_path)) {
            abort(404, 'Document not found or deleted.');
        }

        return response()->file(storage_path('app/private/' . $job->file_path));
    }

    public function updateJobStatus(Request $request, PrintJob $job)
    {
        $data = $request->validate([
            'status' => 'required|in:success,failed,processing,pending',
        ]);

        $job->update(['status' => $data['status']]);

        return redirect()->back()->with('success', "Job status updated to {$data['status']}");
    }
}
