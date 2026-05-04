<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PrintAgent;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $agents = PrintAgent::with(['branch.company'])->withCount('jobs')->latest()->get();
        $branches = Branch::with('company')->active()->orderBy('name')->get();
        return view('admin.agents', compact('agents', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'branch_id'  => 'nullable|exists:branches,id',
            'location'   => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
        ]);

        $rawKey = Str::random(32);

        PrintAgent::create([
            'name'       => $data['name'],
            'agent_key'  => PrintAgent::hashKey($rawKey),
            'branch_id'  => $data['branch_id'] ?? null,
            'location'   => $data['location'] ?? null,
            'department' => $data['department'] ?? null,
        ]);

        $this->logActivity('agent.created', null, ['name' => $data['name']]);

        return redirect()->route('admin.agents')->with('success', "Agent created! Copy this key — it won't be shown again: <code style=\"background:var(--bg);padding:2px 6px;border-radius:4px;\">{$rawKey}</code>");
    }

    public function update(Request $request, PrintAgent $agent)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'branch_id'  => 'nullable|exists:branches,id',
            'location'   => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'is_active'  => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');

        $this->logActivity('agent.updated', $agent, [
            'name' => $data['name'],
            'changes' => array_diff_assoc($data, $agent->only(array_keys($data))),
        ]);

        $agent->update($data);

        return redirect()->route('admin.agents')->with('success', 'Agent updated!');
    }

    public function regenerateKey(PrintAgent $agent)
    {
        $rawKey = Str::random(32);
        $agent->update([
            'agent_key'          => PrintAgent::hashKey($rawKey),
            'last_key_rotated_at' => now(),
        ]);

        $this->logActivity('agent.key_regenerated', $agent, ['name' => $agent->name]);

        return redirect()->route('admin.agents')->with('success', "Agent key regenerated! Copy this key — it won't be shown again: <code style=\"background:var(--bg);padding:2px 6px;border-radius:4px;\">{$rawKey}</code>");
    }

    public function destroy(PrintAgent $agent)
    {
        $this->logActivity('agent.deleted', $agent, ['name' => $agent->name]);
        $agent->delete();
        return redirect()->route('admin.agents')->with('success', 'Agent removed.');
    }
}
