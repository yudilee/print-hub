<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrinterConfig;
use App\Models\PrintAgent;
use Illuminate\Http\Request;

class PrinterConfigController extends Controller
{
    /**
     * Display a listing of per-printer configurations.
     */
    public function index(Request $request)
    {
        $query = PrinterConfig::with('agent.branch');

        if ($request->filled('print_agent_id')) {
            $query->where('print_agent_id', $request->print_agent_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('printer_name', 'like', "%{$search}%")
                  ->orWhereHas('agent', fn ($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        $configs = $query->latest()->paginate(50);
        $agents = PrintAgent::where('is_active', true)->orderBy('name')->get();

        return view('admin.printer-configs.index', compact('configs', 'agents'));
    }

    /**
     * Store a new printer configuration.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'print_agent_id' => 'required|exists:print_agents,id',
            'printer_name'   => 'required|string|max:255',
            'config'         => 'required|json',
            'is_active'      => 'boolean',
        ]);

        $data['config'] = json_decode($data['config'], true);
        $data['is_active'] = $request->boolean('is_active', true);

        $config = PrinterConfig::create($data);

        return redirect()->route('admin.printer-configs')
            ->with('success', "Printer configuration for '{$config->printer_name}' created.");
    }

    /**
     * Show the form to edit a printer configuration.
     */
    public function edit(PrinterConfig $printerConfig)
    {
        $agents = PrintAgent::where('is_active', true)->orderBy('name')->get();
        return view('admin.printer-configs.edit', [
            'config' => $printerConfig,
            'agents' => $agents,
        ]);
    }

    /**
     * Update the specified printer configuration.
     */
    public function update(Request $request, PrinterConfig $printerConfig)
    {
        $data = $request->validate([
            'print_agent_id' => 'required|exists:print_agents,id',
            'printer_name'   => 'required|string|max:255',
            'config'         => 'required|json',
            'is_active'      => 'boolean',
        ]);

        $data['config'] = json_decode($data['config'], true);
        $data['is_active'] = $request->boolean('is_active', true);

        $printerConfig->update($data);

        return redirect()->route('admin.printer-configs')
            ->with('success', "Printer configuration for '{$printerConfig->printer_name}' updated.");
    }

    /**
     * Remove the specified printer configuration.
     */
    public function destroy(PrinterConfig $printerConfig)
    {
        $name = $printerConfig->printer_name;
        $printerConfig->delete();

        return redirect()->route('admin.printer-configs')
            ->with('success', "Printer configuration for '{$name}' deleted.");
    }
}
