<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Display the system settings page.
     */
    public function index()
    {
        $settings = Setting::all()->keyBy('key');

        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Update system settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            // General
            'app_name'              => 'required|string|max:255',
            'timezone'              => 'required|string|timezone',
            'default_locale'        => 'required|string|max:10',
            // Print Defaults
            'default_copies'        => 'required|integer|min:1|max:999',
            'default_duplex_mode'   => 'required|string|in:none,short-edge,long-edge',
            'default_paper_size'    => 'required|string|max:50',
            // Job Retention
            'retain_completed_days' => 'required|integer|min:1|max:365',
            'retain_failed_days'    => 'required|integer|min:1|max:365',
            // Rate Limiting
            'rate_limit_client_app' => 'required|integer|min:1|max:10000',
            'rate_limit_agent'      => 'required|integer|min:1|max:10000',
            // Webhook Defaults
            'webhook_default_retry' => 'required|integer|min:0|max:25',
            'webhook_default_timeout'=> 'required|integer|min:5|max:300',
            // API Key Rotation Policy (Item 12.4)
            'key_rotation_days'     => 'nullable|integer|min:1|max:365',
            // Per-App/Agent Rate Limits (Item 15.1)
            'max_requests_per_minute_client' => 'nullable|integer|min:1|max:10000',
            'max_requests_per_minute_agent'  => 'nullable|integer|min:1|max:10000',
            // Session Management (Item 12.2)
            'session_expiry_minutes' => 'nullable|integer|min:1|max:1440',
            // Group Policies (Item 19.1)
            'policy_force_tls'                => 'nullable|in:0,1',
            'policy_min_key_length'           => 'nullable|integer|min:16|max:128',
            'policy_allowed_auth_providers'   => 'nullable|string|max:500',
            'policy_session_timeout_minutes'  => 'nullable|integer|min:1|max:1440',
            'policy_audit_log_retention_days' => 'nullable|integer|min:1|max:3650',
        ]);

        foreach ($validated as $key => $value) {
            $existing = Setting::where('key', $key)->first();
            $type = $existing ? $existing->type : 'string';

            // Determine the correct type for group policy fields
            if (str_starts_with($key, 'policy_')) {
                $type = match ($key) {
                    'policy_force_tls'                => 'boolean',
                    'policy_min_key_length'           => 'integer',
                    'policy_allowed_auth_providers'   => 'json',
                    'policy_session_timeout_minutes'  => 'integer',
                    'policy_audit_log_retention_days' => 'integer',
                    default                           => 'string',
                };

                // Convert comma-separated providers string to JSON array
                if ($key === 'policy_allowed_auth_providers' && is_string($value)) {
                    $providers = array_map('trim', explode(',', $value));
                    $providers = array_filter($providers);
                    $value = $providers;
                }
            }

            Setting::setValue($key, $value, $type);
        }

        return redirect()->route('admin.settings')
            ->with('success', 'System settings updated successfully.');
    }
}
