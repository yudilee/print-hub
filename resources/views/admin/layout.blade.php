<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Print Hub') — Central Print Management</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root, [data-theme="dark"] {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface-hover: #22263a;
            --border: #2a2e3f;
            --text: #e4e6ed;
            --text-muted: #8b8fa3;
            --primary: #6366f1;
            --primary-hover: #818cf8;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --toast-bg: #1a1d27;
            --toast-border: #2a2e3f;
        }

        [data-theme="light"] {
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-hover: #f1f5f9;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-muted: #64748b;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
            --info: #2563eb;
            --toast-bg: #ffffff;
            --toast-border: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 240px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 1.5rem 0;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 900;
            transition: transform 0.25s ease;
        }
        .main { margin-left: 240px; flex: 1; padding: 2rem; }

        .sidebar-brand {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }
        .sidebar-brand h1 {
            font-size: 1.1rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .sidebar-brand small { color: var(--text-muted); font-size: 0.75rem; }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s;
            border-left: 3px solid transparent;
        }
        .nav-link:hover { color: var(--text); background: var(--surface-hover); }
        .nav-link.active {
            color: var(--primary-hover);
            background: rgba(99, 102, 241, 0.08);
            border-left-color: var(--primary);
        }
        .nav-section {
            padding: 1.5rem 1.5rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Mobile hamburger */
        .hamburger { display: none; position: fixed; top: 0.75rem; left: 0.75rem; z-index: 910;
            background: var(--surface); border: 1px solid var(--border); color: var(--text);
            padding: 0.5rem 0.6rem; border-radius: 6px; cursor: pointer; font-size: 1.2rem; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 899; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .card-header h2 { font-size: 1rem; font-weight: 600; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            transition: transform 0.15s, border-color 0.15s;
        }
        .stat-card:hover { transform: translateY(-2px); border-color: var(--primary); }
        .stat-value { font-size: 2rem; font-weight: 700; margin-bottom: 0.25rem; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tbody tr:hover { background: var(--surface-hover); }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-success { background: rgba(34, 197, 94, 0.15); color: var(--success); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: var(--info); }

        .dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .dot-green { background: var(--success); box-shadow: 0 0 6px var(--success); }
        .dot-red { background: var(--danger); }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: rgba(255, 255, 255, 0.08); color: var(--text); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.15); }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.25); }
        .btn-warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .btn-warning:hover { background: rgba(245, 158, 11, 0.25); }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }

        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.3rem;
        }
        input[type="text"], input[type="number"], input[type="email"], input[type="password"], input[type="url"], select, textarea {
            width: 100%;
            padding: 0.55rem 0.75rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.85rem;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        input:invalid, select:invalid, textarea:invalid { border-color: var(--danger); }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-inline { display: flex; gap: 0.5rem; align-items: end; }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .alert-success { background: rgba(34, 197, 94, 0.1); color: var(--success); border: 1px solid rgba(34, 197, 94, 0.2); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

        .mono { font-family: 'Fira Code', 'Cascadia Code', monospace; font-size: 0.8rem; background: var(--bg); padding: 0.2rem 0.5rem; border-radius: 4px; }

        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 1.5rem; font-weight: 700; }
        .page-header p { color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem; }

        .filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; align-items: center; }
        .filter-bar select { width: auto; min-width: 150px; }

        .pagination { display: flex; gap: 0.25rem; margin-top: 1rem; justify-content: center; flex-wrap: wrap; }
        .pagination a, .pagination span {
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-decoration: none;
            border: 1px solid var(--border);
        }
        .pagination a:hover { background: var(--surface-hover); color: var(--text); }
        .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }

        /* Toast notifications */
        .toast-container {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
            display: flex; flex-direction: column; gap: 0.5rem; max-width: 380px;
        }
        .toast {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            background: var(--toast-bg);
            border: 1px solid var(--toast-border);
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.85rem;
            animation: toastIn 0.3s ease;
            transition: opacity 0.3s, transform 0.3s;
        }
        .toast-removing { opacity: 0; transform: translateX(100%); }
        .toast-success { border-left: 3px solid var(--success); }
        .toast-error { border-left: 3px solid var(--danger); }
        .toast-warning { border-left: 3px solid var(--warning); }
        .toast-info { border-left: 3px solid var(--info); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }

        /* Spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 16px; height: 16px; border: 2px solid currentColor;
            border-right-color: transparent; border-radius: 50%;
            animation: spin 0.6s linear infinite;
            display: inline-block;
        }

        /* Theme toggle */
        .theme-toggle {
            background: none; border: 1px solid var(--border); color: var(--text-muted);
            padding: 0.35rem 0.55rem; border-radius: 6px; cursor: pointer;
            display: flex; align-items: center; gap: 0.3rem; font-size: 0.85rem;
        }
        .theme-toggle:hover { color: var(--text); }

        /* Expandable section */
        .expandable { cursor: pointer; user-select: none; }
        .expandable-content { display: none; }
        .expandable-content.open { display: block; }

        /* Form field error */
        .field-error { color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem; display: none; }
        .field-error.visible { display: block; }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .hamburger { display: block; }
            .main { margin-left: 0; padding: 1rem; }
            .form-row { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table { display: block; overflow-x: auto; }
            .filter-bar { flex-wrap: wrap; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        /* Help tooltip */
        .help-tip {
            display: inline-flex; align-items: center; justify-content: center;
            width: 16px; height: 16px; border-radius: 50%;
            background: var(--border); color: var(--text-muted);
            font-size: 10px; font-weight: 700; cursor: help;
            vertical-align: middle; margin-left: 4px;
            position: relative;
        }
        .help-tip-popover {
            display: none; position: absolute; bottom: calc(100% + 8px); left: 50%;
            transform: translateX(-50%); width: 220px; padding: 0.6rem 0.75rem;
            background: var(--surface); border: 1px solid var(--border); border-radius: 6px;
            font-size: 0.78rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 100; white-space: normal; font-weight: 400; line-height: 1.4;
        }
        .help-tip:hover .help-tip-popover { display: block; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu" title="Menu">☰</button>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <h1>Print Hub</h1>
                <small>Central Management</small>
            </div>
            <nav>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <x-icon name="dashboard" size="18"/> Dashboard
                </a>
                <a href="{{ route('admin.agents') }}" class="nav-link {{ request()->routeIs('admin.agents') ? 'active' : '' }}">
                    <x-icon name="agent" size="18"/> Agents
                </a>
                <a href="{{ route('admin.profiles') }}" class="nav-link {{ request()->routeIs('admin.profiles') ? 'active' : '' }}">
                    <x-icon name="queue" size="18"/> Print Queues
                </a>
                <a href="{{ route('admin.jobs') }}" class="nav-link {{ request()->routeIs('admin.jobs') ? 'active' : '' }}">
                    <x-icon name="job" size="18"/> Job History
                </a>
                <a href="{{ route('admin.templates') }}" class="nav-link {{ request()->routeIs('admin.templates*') ? 'active' : '' }}">
                    <x-icon name="template" size="18"/> Print Templates
                </a>

                @if(auth()->user()?->isSuperAdmin())
                <a href="{{ route('admin.clients') }}" class="nav-link {{ request()->routeIs('admin.clients') ? 'active' : '' }}">
                    <x-icon name="clients" size="18"/> Client Apps
                </a>
                @endif

                <div class="nav-section">Organization</div>
                @if(auth()->user()?->isSuperAdmin())
                <a href="{{ route('admin.companies') }}" class="nav-link {{ request()->routeIs('admin.companies') ? 'active' : '' }}">
                    <x-icon name="company" size="18"/> Companies
                </a>
                @endif
                @if(auth()->user()?->hasAnyRole(['super-admin', 'company-admin', 'branch-admin']))
                <a href="{{ route('admin.branches') }}" class="nav-link {{ request()->routeIs('admin.branches*') ? 'active' : '' }}">
                    <x-icon name="branch" size="18"/> Branches
                </a>
                @endif

                <div class="nav-section">Access Control</div>
                @if(auth()->user()?->hasAnyRole(['super-admin', 'company-admin']))
                <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                    <x-icon name="users" size="18"/> Users
                </a>
                @endif
                <a href="{{ route('admin.sessions') }}" class="nav-link {{ request()->routeIs('admin.sessions') ? 'active' : '' }}">
                    <x-icon name="sessions" size="18"/> Active Sessions
                </a>
                @if(auth()->user()?->hasAnyRole(['super-admin', 'company-admin', 'branch-admin']))
                <a href="{{ route('admin.activity-logs') }}" class="nav-link {{ request()->routeIs('admin.activity-logs') ? 'active' : '' }}">
                    <x-icon name="activity" size="18"/> Activity Log
                </a>
                @endif

                <div class="nav-section">Help</div>
                <a href="{{ route('admin.sdk-docs') }}" class="nav-link {{ request()->routeIs('admin.sdk-docs') ? 'active' : '' }}">
                    <x-icon name="docs" size="18"/> Documentation
                </a>
            </nav>

            <div style="margin-top: auto; padding: 1.5rem; border-top: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">
                        <span id="theme-icon">☀️</span>
                    </button>
                </div>
                @auth
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white;">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div style="flex: 1; overflow: hidden;">
                        <div style="font-size: 0.85rem; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">{{ auth()->user()->name }}</div>
                        <div style="font-size: 0.7rem; color: var(--text-muted);">{{ ucfirst(str_replace('-', ' ', auth()->user()->role)) }}</div>
                    </div>
                </div>
                @if(auth()->user()->branch)
                <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.75rem; padding: 0.4rem 0.6rem; background: rgba(99, 102, 241, 0.08); border-radius: 4px;">
                    <x-icon name="branch" size="12"/> {{ auth()->user()->branch->name }}
                    @if(auth()->user()->company)
                        <br><x-icon name="company" size="12"/> {{ auth()->user()->company->code }}
                    @endif
                </div>
                @endif
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-danger" style="width: 100%; justify-content: center;">Logout</button>
                </form>
                @endauth
            </div>
        </aside>
        <main class="main">
            @if($errors->any())
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 1.2rem;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success">✓ {!! session('success') !!}</div>
            @endif
            @if(session('key_info'))
                <div class="alert alert-success" style="border-color: var(--primary); background: rgba(99,102,241,0.1); color: var(--primary);">
                    🔑 {!! session('key_info') !!}
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    {{-- Toast container --}}
    <div class="toast-container" id="toast-container"></div>

    <script>
        // Sidebar toggle for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.querySelector('.sidebar-overlay').classList.toggle('open');
        }
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) toggleSidebar();
            });
        });

        // Theme toggle
        (function() {
            const saved = localStorage.getItem('ph-theme');
            if (saved) document.documentElement.setAttribute('data-theme', saved);
            else if (window.matchMedia('(prefers-color-scheme: light)').matches) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
            updateThemeIcon();
        })();
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('ph-theme', next);
            updateThemeIcon();
        }
        function updateThemeIcon() {
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            document.getElementById('theme-icon').textContent = isDark ? '☀️' : '🌙';
        }

        // Toast system
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            toast.innerHTML = '<span style="flex:1;">' + message + '</span>' +
                '<button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.1rem;">&times;</button>';
            container.appendChild(toast);
            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.add('toast-removing');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
        }

        // Show flash messages as toasts
        @if(session('toast_success'))
            showToast('{!! addslashes(session('toast_success')) !!}', 'success');
        @endif
        @if(session('toast_error'))
            showToast('{!! addslashes(session('toast_error')) !!}', 'error');
        @endif

        // Form loading state
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form[data-loading]').forEach(form => {
                form.addEventListener('submit', function() {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.disabled = true;
                        const orig = btn.innerHTML;
                        btn.setAttribute('data-orig', orig);
                        btn.innerHTML = '<span class="spinner"></span> ' + (btn.getAttribute('data-loading-text') || 'Saving...');
                        setTimeout(() => { if (btn.disabled) { btn.innerHTML = orig; btn.disabled = false; } }, 15000);
                    }
                });
            });
        });

        // Expandable sections
        document.querySelectorAll('.expandable').forEach(el => {
            el.addEventListener('click', function() {
                const content = this.nextElementSibling;
                if (content && content.classList.contains('expandable-content')) {
                    content.classList.toggle('open');
                    const arrow = this.querySelector('.expandable-arrow');
                    if (arrow) arrow.textContent = content.classList.contains('open') ? '▾' : '▸';
                }
            });
        });

        // Confirm dialog on all delete buttons
        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm(this.getAttribute('data-confirm'))) e.preventDefault();
            });
        });
    </script>
</body>
</html>
