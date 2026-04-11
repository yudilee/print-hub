<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Print Hub') — Central Print Management</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* Layout */
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
        }
        .main { margin-left: 240px; flex: 1; padding: 2rem; }

        /* Sidebar */
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
        .nav-icon { font-size: 1.1rem; width: 20px; text-align: center; }

        /* Cards */
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

        /* Stats Grid */
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

        /* Tables */
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

        /* Badges */
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

        /* Dot indicator */
        .dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .dot-green { background: var(--success); box-shadow: 0 0 6px var(--success); }
        .dot-red { background: var(--danger); }

        /* Buttons */
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
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.25); }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.3rem;
        }
        input[type="text"], input[type="number"], select {
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
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-inline { display: flex; gap: 0.5rem; align-items: end; }

        /* Alert */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .alert-success { background: rgba(34, 197, 94, 0.1); color: var(--success); border: 1px solid rgba(34, 197, 94, 0.2); }

        /* Monospace for keys */
        .mono { font-family: 'Fira Code', 'Cascadia Code', monospace; font-size: 0.8rem; background: var(--bg); padding: 0.2rem 0.5rem; border-radius: 4px; }

        /* Page title */
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 1.5rem; font-weight: 700; }
        .page-header p { color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem; }

        /* Filter bar */
        .filter-bar {
            display: flex; gap: 0.75rem; margin-bottom: 1rem; align-items: center;
        }
        .filter-bar select { width: auto; min-width: 150px; }

        /* Pagination */
        .pagination { display: flex; gap: 0.25rem; margin-top: 1rem; justify-content: center; }
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
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h1>🖨️ Print Hub</h1>
                <small>Central Management</small>
            </div>
            <nav>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="{{ route('admin.agents') }}" class="nav-link {{ request()->routeIs('admin.agents') ? 'active' : '' }}">
                    <span class="nav-icon">💻</span> Agents
                </a>
                <a href="{{ route('admin.profiles') }}" class="nav-link {{ request()->routeIs('admin.profiles') ? 'active' : '' }}">
                    <span class="nav-icon">📋</span> Queues
                </a>
                <a href="{{ route('admin.jobs') }}" class="nav-link {{ request()->routeIs('admin.jobs') ? 'active' : '' }}">
                    <span class="nav-icon">📜</span> Job History
                </a>
                <a href="{{ route('admin.templates') }}" class="nav-link {{ request()->routeIs('admin.templates*') ? 'active' : '' }}">
                    <span class="nav-icon">🎨</span> Print Templates
                </a>
                <a href="{{ route('admin.clients') }}" class="nav-link {{ request()->routeIs('admin.clients*') ? 'active' : '' }}">
                    <span class="nav-icon">🔑</span> Client Apps
                </a>
            </nav>
        </aside>
        <main class="main">
            @if(session('success'))
                <div class="alert alert-success">✓ {{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
