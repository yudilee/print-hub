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
        $agents = PrintAgent::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'printers', 'branch_id']);

        return view('admin.printer-configs.index', compact('configs', 'agents'));
    }

    /**
     * Store a new printer configuration.
     *
     * Accepts either:
     *  - A raw JSON string in the 'config' field (legacy), or
     *  - Individual structured form fields: copies, duplex, paper_size,
     *    tray, color_mode, print_quality, orientation, media_type,
     *    collate, fit_to_page, plus 'advanced_config' for custom JSON.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'print_agent_id'   => 'required|exists:print_agents,id',
            'printer_name'     => 'required|string|max:255',
            'config'           => 'nullable|json',             // legacy raw JSON
            'copies'           => 'nullable|integer|min:1|max:999',
            'duplex'           => 'nullable|string|max:50',
            'paper_size'       => 'nullable|string|max:50',
            'tray'             => 'nullable|string|max:100',
            'color_mode'       => 'nullable|string|max:50',
            'print_quality'    => 'nullable|string|max:50',
            'orientation'      => 'nullable|string|max:50',
            'media_type'       => 'nullable|string|max:100',
            'collate'          => 'nullable|boolean',
            'fit_to_page'      => 'nullable|boolean',
            'advanced_config'  => 'nullable|json',             // extra custom options
            'is_active'        => 'boolean',
        ]);

        // Build config array from individual fields or legacy JSON
        $config = $this->buildConfigFromRequest($data);

        PrinterConfig::create([
            'print_agent_id' => $data['print_agent_id'],
            'printer_name'   => $data['printer_name'],
            'config'         => $config,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.printer-configs')
            ->with('success', "Printer configuration for '{$data['printer_name']}' created.");
    }

    /**
     * Show the form to edit a printer configuration.
     *
     * Editing is handled via the modal in the index view.
     * Redirect to the index page which contains the modal.
     */
    public function edit(PrinterConfig $printerConfig)
    {
        return redirect()->route('admin.printer-configs')
            ->with('edit_config_id', $printerConfig->id);
    }

    /**
     * Update the specified printer configuration.
     */
    public function update(Request $request, PrinterConfig $printerConfig)
    {
        $data = $request->validate([
            'print_agent_id'   => 'required|exists:print_agents,id',
            'printer_name'     => 'required|string|max:255',
            'config'           => 'nullable|json',
            'copies'           => 'nullable|integer|min:1|max:999',
            'duplex'           => 'nullable|string|max:50',
            'paper_size'       => 'nullable|string|max:50',
            'tray'             => 'nullable|string|max:100',
            'color_mode'       => 'nullable|string|max:50',
            'print_quality'    => 'nullable|string|max:50',
            'orientation'      => 'nullable|string|max:50',
            'media_type'       => 'nullable|string|max:100',
            'collate'          => 'nullable|boolean',
            'fit_to_page'      => 'nullable|boolean',
            'advanced_config'  => 'nullable|json',
            'is_active'        => 'boolean',
        ]);

        $config = $this->buildConfigFromRequest($data);

        $printerConfig->update([
            'print_agent_id' => $data['print_agent_id'],
            'printer_name'   => $data['printer_name'],
            'config'         => $config,
            'is_active'      => $request->boolean('is_active', true),
        ]);

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

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Build the config array from validated request data.
     *
     * Priority:
     *  1. If 'config' (legacy JSON) is provided, use it directly.
     *  2. Otherwise, assemble from individual structured fields,
     *     then merge in 'advanced_config' for custom keys.
     */
    private function buildConfigFromRequest(array $data): array
    {
        // Legacy mode: raw JSON config field
        if (! empty($data['config'])) {
            return json_decode($data['config'], true);
        }

        // Structured fields mode
        $config = [];

        foreach (['copies', 'duplex', 'paper_size', 'tray', 'color_mode',
                   'print_quality', 'orientation', 'media_type'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                $config[$field] = $data[$field];
            }
        }

        foreach (['collate', 'fit_to_page'] as $boolField) {
            if (isset($data[$boolField]) && $data[$boolField] !== '') {
                $config[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Merge advanced custom options on top
        if (! empty($data['advanced_config'])) {
            $advanced = json_decode($data['advanced_config'], true);
            if (is_array($advanced)) {
                $config = array_merge($config, $advanced);
            }
        }

        return $config;
    }
}
