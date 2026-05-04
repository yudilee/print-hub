<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrinterPool;
use App\Models\PrinterPoolPrinter;
use App\Models\PrintAgent;
use Illuminate\Http\Request;

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
}
