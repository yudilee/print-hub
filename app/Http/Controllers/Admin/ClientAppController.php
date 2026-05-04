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
            'name'            => 'required|string|max:255',
            'allowed_origins' => 'nullable|string',
        ]);

        $origins = null;
        if (!empty($data['allowed_origins'])) {
            $origins = array_map('trim', explode(',', $data['allowed_origins']));
        }

        $rawKey = (string) Str::uuid();

        ClientApp::create([
            'name'            => $data['name'],
            'api_key'         => ClientApp::hashKey($rawKey),
            'allowed_origins' => $origins,
        ]);

        return redirect()->route('admin.clients')->with('success', "Client app registered! Copy this API key — it won't be shown again: <code style=\"background:var(--bg);padding:2px 6px;border-radius:4px;\">{$rawKey}</code>");
    }

    public function regenerateKey(ClientApp $client)
    {
        $rawKey = (string) Str::uuid();
        $client->update(['api_key' => ClientApp::hashKey($rawKey)]);

        return redirect()->route('admin.clients')->with('success', "API key regenerated! Copy this key — it won't be shown again: <code style=\"background:var(--bg);padding:2px 6px;border-radius:4px;\">{$rawKey}</code>");
    }

    public function destroy(ClientApp $client)
    {
        $client->delete();
        return redirect()->route('admin.clients')->with('success', 'Client app removed.');
    }
}
