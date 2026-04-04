<?php

namespace App\Http\Controllers;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Models\PrintJob;
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
            'name' => 'required|string|max:255|unique:print_profiles,name',
            'description' => 'nullable|string|max:255',
            'paper_size' => 'required|string',
            'orientation' => 'required|string',
            'copies' => 'required|integer|min:1',
            'duplex' => 'required|string',
            'default_printer' => 'nullable|string|max:255',
        ]);

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
        return view('admin.templates.designer', ['template' => new \App\Models\PrintTemplate()]);
    }

    public function templateStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:print_templates,name',
            'paper_width_mm' => 'required|numeric',
            'paper_height_mm' => 'required|numeric',
            'elements' => 'nullable|array',
            'background_image_path' => 'nullable|string'
        ]);

        \App\Models\PrintTemplate::create($data);

        return response()->json(['status' => 'ok']);
    }

    public function templateEdit(\App\Models\PrintTemplate $template)
    {
        return view('admin.templates.designer', compact('template'));
    }

    public function templateUpdate(Request $request, \App\Models\PrintTemplate $template)
    {
        $data = $request->validate([
            'name' => 'required|unique:print_templates,name,' . $template->id,
            'paper_width_mm' => 'required|numeric',
            'paper_height_mm' => 'required|numeric',
            'elements' => 'nullable|array',
            'background_image_path' => 'nullable|string'
        ]);

        $template->update($data);

        return response()->json(['status' => 'ok']);
    }

    public function templateDestroy(\App\Models\PrintTemplate $template)
    {
        $template->delete();
        return redirect()->route('admin.templates')->with('success', 'Template deleted.');
    }

    public function templateUploadBg(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048'
        ]);

        $path = $request->file('image')->store('template_bg', 'public');

        return response()->json([
            'status' => 'ok',
            'url' => \Illuminate\Support\Facades\Storage::url($path)
        ]);
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
