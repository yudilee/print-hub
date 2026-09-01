@extends('admin.layout')
@section('title', 'System Settings')

@section('content')
<x-breadcrumb :items="[['label' => 'System Settings']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Global Configuration & Group Policies</h2>
        <p class="text-xs text-slate-400">Configure global hub preferences, print defaults, retention policies, and security thresholds</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf
    @method('PUT')

    {{-- General --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
        <h3 class="text-xs font-bold text-blue-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800 flex items-center gap-2">
            <span>🌐 General App Parameters</span>
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Application Name</label>
                <input type="text" name="app_name" value="{{ old('app_name', $settings['app_name']->value ?? config('app.name')) }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">System Timezone</label>
                <select name="timezone" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" {{ (old('timezone', $settings['timezone']->value ?? config('app.timezone'))) === $tz ? 'selected' : '' }}>
                            {{ $tz }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Default Locale</label>
                <select name="default_locale" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    @foreach(['en' => 'English', 'id' => 'Bahasa Indonesia', 'ms' => 'Bahasa Melayu', 'zh' => '中文', 'ja' => '日本語'] as $code => $name)
                        <option value="{{ $code }}" {{ (old('default_locale', $settings['default_locale']->value ?? 'en')) === $code ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Print Defaults --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">
            🖨️ Global Print Fallbacks
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Default Copies</label>
                <input type="number" name="default_copies" min="1" max="999" value="{{ old('default_copies', $settings['default_copies']->value ?? 1) }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Default Duplex Mode</label>
                <select name="default_duplex_mode" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="none" {{ (old('default_duplex_mode', $settings['default_duplex_mode']->value ?? 'none')) === 'none' ? 'selected' : '' }}>None (Simplex)</option>
                    <option value="short-edge" {{ (old('default_duplex_mode', $settings['default_duplex_mode']->value ?? 'none')) === 'short-edge' ? 'selected' : '' }}>Short Edge</option>
                    <option value="long-edge" {{ (old('default_duplex_mode', $settings['default_duplex_mode']->value ?? 'none')) === 'long-edge' ? 'selected' : '' }}>Long Edge</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Default Paper Size</label>
                <select name="default_paper_size" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    @foreach(['A4', 'A3', 'A5', 'Letter', 'Legal', 'Tabloid'] as $size)
                        <option value="{{ $size }}" {{ (old('default_paper_size', $settings['default_paper_size']->value ?? 'A4')) === $size ? 'selected' : '' }}>
                            {{ $size }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Retention & Rate Limits --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
            <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">
                🗄️ Job Retention Policy
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Retain Completed Jobs (Days)</label>
                    <input type="number" name="retain_completed_days" min="1" max="365" value="{{ old('retain_completed_days', $settings['retain_completed_days']->value ?? 30) }}"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Retain Failed Jobs (Days)</label>
                    <input type="number" name="retain_failed_days" min="1" max="365" value="{{ old('retain_failed_days', $settings['retain_failed_days']->value ?? 14) }}"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
            <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">
                🚦 API Rate Limits & Throttling
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Max Requests/Min per Client App</label>
                    <input type="number" name="max_requests_per_minute_client" min="1" max="10000" value="{{ old('max_requests_per_minute_client', $settings['max_requests_per_minute_client']->value ?? 60) }}"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Max Requests/Min per Workstation Agent</label>
                    <input type="number" name="max_requests_per_minute_agent" min="1" max="10000" value="{{ old('max_requests_per_minute_agent', $settings['max_requests_per_minute_agent']->value ?? 120) }}"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>
        </div>
    </div>

    {{-- Enterprise Group Policies --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
        <h3 class="text-xs font-bold text-rose-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">
            🏢 Enterprise Security Policies
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Force TLS Encryption</label>
                <select name="policy_force_tls" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="1" {{ (old('policy_force_tls', $settings['policy_force_tls']->value ?? '0')) === '1' ? 'selected' : '' }}>Enabled (HTTPS/WSS Only)</option>
                    <option value="0" {{ (old('policy_force_tls', $settings['policy_force_tls']->value ?? '0')) === '0' ? 'selected' : '' }}>Disabled (Allow Plaintext)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Session Inactivity Timeout (Mins)</label>
                <input type="number" name="policy_session_timeout_minutes" min="1" max="1440" value="{{ old('policy_session_timeout_minutes', $settings['policy_session_timeout_minutes']->value ?? 480) }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Audit Log Retention (Days)</label>
                <input type="number" name="policy_audit_log_retention_days" min="1" max="3650" value="{{ old('policy_audit_log_retention_days', $settings['policy_audit_log_retention_days']->value ?? 90) }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <button type="reset" class="btn-secondary btn-sm" onclick="event.preventDefault(); window.location.href='{{ route('admin.settings') }}'">Reset</button>
        <button type="submit" class="btn-primary btn-sm">💾 Save System Settings</button>
    </div>
</form>
@endsection
