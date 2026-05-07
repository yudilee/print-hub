@extends('admin.layout')
@section('title', 'System Settings')

@section('content')
<x-breadcrumb :items="[['label' => 'System Settings']]" />

<div class="page-header">
    <h1>⚙️ System Settings</h1>
    <p>Configure global system preferences, print defaults, retention policies, and more.</p>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf
    @method('PUT')

    {{-- General --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🌐 General</h2>
        </div>
        <div style="padding: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <label for="app_name">Application Name</label>
                <input type="text" name="app_name" id="app_name"
                    value="{{ old('app_name', $settings['app_name']->value ?? config('app.name')) }}"
                    placeholder="Print Hub">
                @error('app_name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="timezone">Timezone</label>
                <select name="timezone" id="timezone">
                    @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" {{ (old('timezone', $settings['timezone']->value ?? config('app.timezone'))) === $tz ? 'selected' : '' }}>
                            {{ $tz }}
                        </option>
                    @endforeach
                </select>
                @error('timezone') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="default_locale">Default Locale</label>
                <select name="default_locale" id="default_locale">
                    @foreach(['en' => 'English', 'id' => 'Bahasa Indonesia', 'ms' => 'Bahasa Melayu', 'zh' => '中文', 'ja' => '日本語'] as $code => $name)
                        <option value="{{ $code }}" {{ (old('default_locale', $settings['default_locale']->value ?? 'en')) === $code ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
                @error('default_locale') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Print Defaults --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🖨️ Print Defaults</h2>
        </div>
        <div style="padding: 1rem; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div>
                <label for="default_copies">Default Copies</label>
                <input type="number" name="default_copies" id="default_copies" min="1" max="999"
                    value="{{ old('default_copies', $settings['default_copies']->value ?? 1) }}">
                @error('default_copies') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="default_duplex_mode">Default Duplex Mode</label>
                <select name="default_duplex_mode" id="default_duplex_mode">
                    <option value="none" {{ (old('default_duplex_mode', $settings['default_duplex_mode']->value ?? 'none')) === 'none' ? 'selected' : '' }}>None (Simplex)</option>
                    <option value="short-edge" {{ (old('default_duplex_mode', $settings['default_duplex_mode']->value ?? 'none')) === 'short-edge' ? 'selected' : '' }}>Short Edge</option>
                    <option value="long-edge" {{ (old('default_duplex_mode', $settings['default_duplex_mode']->value ?? 'none')) === 'long-edge' ? 'selected' : '' }}>Long Edge</option>
                </select>
                @error('default_duplex_mode') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="default_paper_size">Default Paper Size</label>
                <select name="default_paper_size" id="default_paper_size">
                    @foreach(['A4', 'A3', 'A5', 'Letter', 'Legal', 'Tabloid'] as $size)
                        <option value="{{ $size }}" {{ (old('default_paper_size', $settings['default_paper_size']->value ?? 'A4')) === $size ? 'selected' : '' }}>
                            {{ $size }}
                        </option>
                    @endforeach
                </select>
                @error('default_paper_size') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Job Retention --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🗄️ Job Retention</h2>
        </div>
        <div style="padding: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <label for="retain_completed_days">Days to Keep Completed Jobs</label>
                <input type="number" name="retain_completed_days" id="retain_completed_days" min="1" max="365"
                    value="{{ old('retain_completed_days', $settings['retain_completed_days']->value ?? 30) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Jobs with status "success" older than this will be cleaned up.
                </div>
                @error('retain_completed_days') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="retain_failed_days">Days to Keep Failed Jobs</label>
                <input type="number" name="retain_failed_days" id="retain_failed_days" min="1" max="365"
                    value="{{ old('retain_failed_days', $settings['retain_failed_days']->value ?? 14) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Jobs with status "failed" older than this will be cleaned up.
                </div>
                @error('retain_failed_days') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Rate Limiting --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🚦 Rate Limiting</h2>
        </div>
        <div style="padding: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <label for="rate_limit_client_app">Max Requests/Minute per Client App (legacy)</label>
                <input type="number" name="rate_limit_client_app" id="rate_limit_client_app" min="1" max="10000"
                    value="{{ old('rate_limit_client_app', $settings['rate_limit_client_app']->value ?? 60) }}">
                @error('rate_limit_client_app') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="rate_limit_agent">Max Requests/Minute per Agent (legacy)</label>
                <input type="number" name="rate_limit_agent" id="rate_limit_agent" min="1" max="10000"
                    value="{{ old('rate_limit_agent', $settings['rate_limit_agent']->value ?? 120) }}">
                @error('rate_limit_agent') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="max_requests_per_minute_client">Max Requests/Minute per Client App (per-key)</label>
                <input type="number" name="max_requests_per_minute_client" id="max_requests_per_minute_client" min="1" max="10000"
                    value="{{ old('max_requests_per_minute_client', $settings['max_requests_per_minute_client']->value ?? 60) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Applied by the ThrottleApiKeys middleware per API key.
                </div>
                @error('max_requests_per_minute_client') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="max_requests_per_minute_agent">Max Requests/Minute per Agent (per-key)</label>
                <input type="number" name="max_requests_per_minute_agent" id="max_requests_per_minute_agent" min="1" max="10000"
                    value="{{ old('max_requests_per_minute_agent', $settings['max_requests_per_minute_agent']->value ?? 120) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Applied by the ThrottleApiKeys middleware per agent key.
                </div>
                @error('max_requests_per_minute_agent') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Webhook Defaults --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🔗 Webhook Defaults</h2>
        </div>
        <div style="padding: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <label for="webhook_default_retry">Default Retry Count</label>
                <input type="number" name="webhook_default_retry" id="webhook_default_retry" min="0" max="25"
                    value="{{ old('webhook_default_retry', $settings['webhook_default_retry']->value ?? 3) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Number of retry attempts for failed webhook deliveries.
                </div>
                @error('webhook_default_retry') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="webhook_default_timeout">Default Timeout (seconds)</label>
                <input type="number" name="webhook_default_timeout" id="webhook_default_timeout" min="5" max="300"
                    value="{{ old('webhook_default_timeout', $settings['webhook_default_timeout']->value ?? 30) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Timeout in seconds for webhook HTTP requests.
                </div>
                @error('webhook_default_timeout') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- API Key Rotation Policy --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🔑 API Key Rotation Policy</h2>
        </div>
        <div style="padding: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <label for="key_rotation_days">Key Rotation Interval (days)</label>
                <input type="number" name="key_rotation_days" id="key_rotation_days" min="1" max="365"
                    value="{{ old('key_rotation_days', $settings['key_rotation_days']->value ?? 90) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Warning banners appear on agent/client pages when a key exceeds this age.
                </div>
                @error('key_rotation_days') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="session_expiry_minutes">Session Expiry (minutes)</label>
                <input type="number" name="session_expiry_minutes" id="session_expiry_minutes" min="1" max="1440"
                    value="{{ old('session_expiry_minutes', $settings['session_expiry_minutes']->value ?? 480) }}">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Admin web sessions will expire after this period of inactivity.
                </div>
                @error('session_expiry_minutes') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Group Policies (Item 19.1) --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🏢 Group Policies</h2>
        </div>
        <div style="padding: 1rem;">
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">
                Enterprise deployment policies that are enforced across all agents and client apps.
                Settings are prefixed with <code>policy_</code> in the database.
            </p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label for="policy_force_tls">Force TLS</label>
                    <select name="policy_force_tls" id="policy_force_tls">
                        <option value="1" {{ (old('policy_force_tls', $settings['policy_force_tls']->value ?? '0')) === '1' ? 'selected' : '' }}>Enabled</option>
                        <option value="0" {{ (old('policy_force_tls', $settings['policy_force_tls']->value ?? '0')) === '0' ? 'selected' : '' }}>Disabled</option>
                    </select>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Enforce TLS for all API and agent connections.
                    </div>
                    @error('policy_force_tls') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="policy_min_key_length">Minimum Key Length</label>
                    <input type="number" name="policy_min_key_length" id="policy_min_key_length" min="16" max="128"
                        value="{{ old('policy_min_key_length', $settings['policy_min_key_length']->value ?? 32) }}">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Minimum allowed length for API keys (characters).
                    </div>
                    @error('policy_min_key_length') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="policy_allowed_auth_providers">Allowed Auth Providers</label>
                    <input type="text" name="policy_allowed_auth_providers" id="policy_allowed_auth_providers"
                        value="{{ old('policy_allowed_auth_providers', $settings['policy_allowed_auth_providers']->value ?? 'local') }}"
                        placeholder="local,google,github">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Comma-separated list of allowed SSO/authentication providers.
                    </div>
                    @error('policy_allowed_auth_providers') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="policy_session_timeout_minutes">Session Timeout (minutes)</label>
                    <input type="number" name="policy_session_timeout_minutes" id="policy_session_timeout_minutes" min="1" max="1440"
                        value="{{ old('policy_session_timeout_minutes', $settings['policy_session_timeout_minutes']->value ?? 480) }}">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Maximum admin session idle time enforced by group policy.
                    </div>
                    @error('policy_session_timeout_minutes') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="policy_audit_log_retention_days">Audit Log Retention (days)</label>
                    <input type="number" name="policy_audit_log_retention_days" id="policy_audit_log_retention_days" min="1" max="3650"
                        value="{{ old('policy_audit_log_retention_days', $settings['policy_audit_log_retention_days']->value ?? 90) }}">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Number of days to retain activity/audit logs.
                    </div>
                    @error('policy_audit_log_retention_days') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-bottom: 2rem;">
        <button type="reset" class="btn btn-secondary" onclick="event.preventDefault(); window.location.href='{{ route('admin.settings') }}'">Reset</button>
        <button type="submit" class="btn btn-primary">💾 Save Settings</button>
    </div>
</form>

<style>
    label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.35rem;
        color: var(--text);
    }
    input[type="text"],
    input[type="number"],
    select {
        width: 100%;
        padding: 0.5rem 0.65rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg);
        color: var(--text);
        font-size: 0.85rem;
        transition: border-color 0.2s;
    }
    input:focus,
    select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }
    .field-error {
        display: block;
        font-size: 0.75rem;
        color: var(--danger);
        margin-top: 0.2rem;
    }
</style>
@endsection
