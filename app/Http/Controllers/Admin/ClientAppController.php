<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientApp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientAppController extends Controller
{
    public function index()
    {
        $clients = ClientApp::latest()->get();
        return view('admin.clients', compact('clients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'allowed_origins'   => 'nullable|string',
            'webhook_url'       => 'nullable|url|max:500',
            'webhook_secret'    => 'nullable|string|max:255',
            'webhook_retry_count' => 'nullable|integer|min:0|max:10',
            'webhook_timeout'   => 'nullable|integer|min:1|max:30',
            'webhook_events'    => 'nullable|string',
        ]);

        $origins = null;
        if (!empty($data['allowed_origins'])) {
            $origins = array_map('trim', explode(',', $data['allowed_origins']));
        }

        $webhookEvents = null;
        if (!empty($data['webhook_events'])) {
            $webhookEvents = array_map('trim', explode(',', $data['webhook_events']));
        }

        $rawKey = (string) Str::uuid();

        ClientApp::create([
            'name'               => $data['name'],
            'api_key'            => ClientApp::hashKey($rawKey),
            'allowed_origins'    => $origins,
            'webhook_url'        => $data['webhook_url'] ?? null,
            'webhook_secret'     => $data['webhook_secret'] ?? null,
            'webhook_retry_count' => $data['webhook_retry_count'] ?? 3,
            'webhook_timeout'    => $data['webhook_timeout'] ?? 5,
            'webhook_events'     => $webhookEvents,
        ]);

        return redirect()->route('admin.clients')->with('success', "Client app registered! Copy this API key — it won't be shown again: <code style=\"background:var(--bg);padding:2px 6px;border-radius:4px;\">{$rawKey}</code>");
    }

    public function regenerateKey(ClientApp $client)
    {
        $rawKey = (string) Str::uuid();
        $client->update([
            'api_key'            => ClientApp::hashKey($rawKey),
            'last_key_rotated_at' => now(),
        ]);

        return redirect()->route('admin.clients')->with('success', "API key regenerated! Copy this key — it won't be shown again: <code style=\"background:var(--bg);padding:2px 6px;border-radius:4px;\">{$rawKey}</code>");
    }

    public function destroy(ClientApp $client)
    {
        $client->delete();
        return redirect()->route('admin.clients')->with('success', 'Client app removed.');
    }
}
