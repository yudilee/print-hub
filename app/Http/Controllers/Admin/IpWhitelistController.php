<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientApp;
use App\Models\PrintAgent;
use Illuminate\Http\Request;

class IpWhitelistController extends Controller
{
    /**
     * Display the IP whitelist management page.
     */
    public function index()
    {
        $clientApps = ClientApp::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'allowed_ips', 'allowed_origins']);

        $agents = PrintAgent::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'allowed_ips', 'ip_address']);

        $globalWhitelistRaw = config('app.api_ip_whitelist', '');
        $globalWhitelist = $globalWhitelistRaw ? explode(',', $globalWhitelistRaw) : [];

        return view('admin.ip-whitelist', compact('clientApps', 'agents', 'globalWhitelist'));
    }

    /**
     * Update allowed IPs for a client app.
     */
    public function updateClientApp(Request $request, ClientApp $clientApp)
    {
        $data = $request->validate([
            'allowed_ips' => 'nullable|string',
        ]);

        $ips = null;
        if (!empty($data['allowed_ips'])) {
            $ips = array_map('trim', explode("\n", str_replace("\r\n", "\n", $data['allowed_ips'])));
            $ips = array_filter($ips, fn($ip) => !empty($ip));
            $ips = array_values($ips);
        }

        $clientApp->update(['allowed_ips' => $ips]);

        return redirect()->route('admin.ip-whitelist')
            ->with('success', "IP whitelist updated for client app '{$clientApp->name}'.");
    }

    /**
     * Update allowed IPs for a print agent.
     */
    public function updateAgent(Request $request, PrintAgent $agent)
    {
        $data = $request->validate([
            'allowed_ips' => 'nullable|string',
        ]);

        $ips = null;
        if (!empty($data['allowed_ips'])) {
            $ips = array_map('trim', explode("\n", str_replace("\r\n", "\n", $data['allowed_ips'])));
            $ips = array_filter($ips, fn($ip) => !empty($ip));
            $ips = array_values($ips);
        }

        $agent->update(['allowed_ips' => implode("\n", $ips)]);

        return redirect()->route('admin.ip-whitelist')
            ->with('success', "IP whitelist updated for agent '{$agent->name}'.");
    }
}
