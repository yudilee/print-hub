<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrinterPool;
use App\Models\PrinterPoolPrinter;
use App\Models\PrintAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PoolController extends Controller
{
    /**
     * List all printer pools.
     */
    public function index()
    {
        $pools = PrinterPool::with('printers')->latest()->get();
        return view('admin.pools.index', compact('pools'));
    }

    /**
     * Show create/edit form for a pool.
     */
    public function edit(?PrinterPool $pool = null)
    {
        $pool = $pool ?? new PrinterPool();
        if ($pool->exists) {
            $pool->load('printers');
        }

        $agents = PrintAgent::where('is_active', true)->get();
        // Collect all unique printer names from all active agents
        $allPrinters = $agents->pluck('printers')->flatten()->unique()->sort()->values();

        return view('admin.pools.edit', compact('pool', 'agents', 'allPrinters'));
    }

    /**
     * Store a new pool.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:printer_pools,name',
            'description' => 'nullable|string|max:500',
            'strategy'    => 'required|string|in:round_robin,least_busy,random,failover',
            'active'      => 'nullable|boolean',
        ]);

        // Branch and agent validation
        if ($request->has('printers') && !empty($request->input('printers'))) {
            $printerNames = collect($request->input('printers'))->pluck('name')->filter()->unique()->all();
            $activeAgents = PrintAgent::where('is_active', true)->get();
            $branchIds = [];

            foreach ($printerNames as $printerName) {
                $ownerAgents = $activeAgents->filter(function ($agent) use ($printerName) {
                    return in_array($printerName, $agent->printers ?? []);
                });

                if ($ownerAgents->isEmpty()) {
                    return redirect()->back()
                        ->withErrors(['printers' => "Printer '{$printerName}' does not exist on any active agent."])
                        ->withInput();
                }

                foreach ($ownerAgents as $oa) {
                    $branchIds[] = $oa->branch_id;
                }
            }

            $uniqueBranchIds = array_unique($branchIds);
            if (count($uniqueBranchIds) > 1) {
                return redirect()->back()
                    ->withErrors(['printers' => 'All printers in the pool must belong to agents in the same branch.'])
                    ->withInput();
            }
        }

        $data['active'] = $request->has('active');

        $pool = PrinterPool::create($data);

        // Attach printers if provided
        if ($request->has('printers')) {
            $printers = $request->validate([
                'printers'          => 'nullable|array',
                'printers.*.name'     => 'required|string|max:255',
                'printers.*.priority' => 'nullable|integer|min:0',
            ]);

            foreach ($printers['printers'] as $idx => $printerData) {
                $pool->printers()->create([
                    'printer_name' => $printerData['name'],
                    'priority'     => $printerData['priority'] ?? $idx,
                    'active'       => true,
                ]);
            }
        }

        return redirect()->route('admin.pools')->with('success', 'Printer pool created.');
    }

    /**
     * Update an existing pool.
     */
    public function update(Request $request, PrinterPool $pool)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:printer_pools,name,' . $pool->id,
            'description' => 'nullable|string|max:500',
            'strategy'    => 'required|string|in:round_robin,least_busy,random,failover',
            'active'      => 'nullable|boolean',
        ]);

        // Branch and agent validation
        if ($request->has('printers') && !empty($request->input('printers'))) {
            $printerNames = collect($request->input('printers'))->pluck('name')->filter()->unique()->all();
            $activeAgents = PrintAgent::where('is_active', true)->get();
            $branchIds = [];

            foreach ($printerNames as $printerName) {
                $ownerAgents = $activeAgents->filter(function ($agent) use ($printerName) {
                    return in_array($printerName, $agent->printers ?? []);
                });

                if ($ownerAgents->isEmpty()) {
                    return redirect()->back()
                        ->withErrors(['printers' => "Printer '{$printerName}' does not exist on any active agent."])
                        ->withInput();
                }

                foreach ($ownerAgents as $oa) {
                    $branchIds[] = $oa->branch_id;
                }
            }

            $uniqueBranchIds = array_unique($branchIds);
            if (count($uniqueBranchIds) > 1) {
                return redirect()->back()
                    ->withErrors(['printers' => 'All printers in the pool must belong to agents in the same branch.'])
                    ->withInput();
            }
        }

        $data['active'] = $request->has('active');

        $pool->update($data);

        // Sync printers
        if ($request->has('printers')) {
            $printers = $request->validate([
                'printers'          => 'nullable|array',
                'printers.*.name'     => 'required|string|max:255',
                'printers.*.priority' => 'nullable|integer|min:0',
                'printers.*.active'   => 'nullable|boolean',
            ]);

            // Remove existing printers and re-add
            $pool->printers()->delete();

            foreach ($printers['printers'] as $idx => $printerData) {
                $pool->printers()->create([
                    'printer_name' => $printerData['name'],
                    'priority'     => $printerData['priority'] ?? $idx,
                    'active'       => $printerData['active'] ?? true,
                ]);
            }
        }

        return redirect()->route('admin.pools')->with('success', 'Printer pool updated.');
    }

    /**
     * Delete a pool.
     */
    public function destroy(PrinterPool $pool)
    {
        $pool->delete();
        return redirect()->route('admin.pools')->with('success', 'Printer pool removed.');
    }

    /**
     * Reset health status for printers in a pool.
     * If `printer_name` is provided, only that printer is reset.
     * Otherwise, all printers in the pool are reset.
     */
    public function resetHealth(Request $request, PrinterPool $pool)
    {
        $query = PrinterPoolPrinter::where('pool_id', $pool->id);

        if ($request->has('printer_name')) {
            $query->where('printer_name', $request->input('printer_name'));
        }

        $count = $query->update([
            'is_healthy'          => true,
            'failure_count'       => 0,
            'last_error_at'       => null,
            'last_error_message'  => null,
            'last_healthy_at'     => now(),
        ]);

        Log::info("Health reset for {$count} printer(s) in pool '{$pool->name}'.");

        return redirect()->route('admin.pools.edit', $pool)
            ->with('success', "Health status reset for {$count} printer(s) in pool.");
    }
}
