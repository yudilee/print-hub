<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Portal') — Print Hub</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Theme Initialization -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || localStorage.getItem('ph-theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('head')
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans selection:bg-blue-500 selection:text-white">

    <!-- Mobile Drawer Overlay -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs z-40 md:hidden hidden transition-opacity"></div>

    <div class="flex-1 flex min-h-screen">
        <!-- Sidebar Navigation -->
        <aside id="sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0 fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
            <!-- Brand Header -->
            <div class="h-16 px-5 border-b border-slate-800 flex items-center justify-between shrink-0">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-xl bg-slate-950 border border-slate-700/60 p-1 flex items-center justify-center shadow-md shadow-teal-500/10 group-hover:scale-105 transition">
                        <img src="{{ asset('logo-icon.png') }}" alt="Print Hub Logo" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <span class="block text-sm font-bold tracking-tight text-white group-hover:text-blue-400 transition">Print Hub</span>
                        <span class="block text-[10px] text-slate-400 font-mono">Central Management</span>
                    </div>
                </a>
                <button type="button" onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" aria-label="Close menu">
                    <x-icon name="x" size="18" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-3 py-4 space-y-6 overflow-y-auto sidebar-scroll" aria-label="Main Navigation">
                
                {{-- Group 1: Core Operations --}}
                <div class="space-y-1">
                    <div class="px-3 pb-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Core Operations</div>
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.dashboard') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="dashboard" size="16" class="{{ request()->routeIs('admin.dashboard') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('admin.monitoring') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.monitoring') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="monitor" size="16" class="{{ request()->routeIs('admin.monitoring') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Monitoring</span>
                    </a>
                    <a href="{{ route('admin.costs') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.costs*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="dollar" size="16" class="{{ request()->routeIs('admin.costs*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Cost Tracking</span>
                    </a>
                    <a href="{{ route('admin.profiles') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.profiles') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="queue" size="16" class="{{ request()->routeIs('admin.profiles') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Print Queues</span>
                    </a>
                    <a href="{{ route('admin.jobs') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.jobs') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="job" size="16" class="{{ request()->routeIs('admin.jobs') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Job History</span>
                    </a>
                </div>

                {{-- Group 2: Print Assets & Design --}}
                <div class="space-y-1">
                    <div class="px-3 pb-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Print Assets</div>
                    <a href="{{ route('admin.templates') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.templates*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="template" size="16" class="{{ request()->routeIs('admin.templates*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Templates</span>
                    </a>
                    <a href="{{ route('admin.fonts') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.fonts*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="font" size="16" class="{{ request()->routeIs('admin.fonts*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Fonts</span>
                    </a>
                    <a href="{{ route('admin.documents') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.documents*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="document" size="16" class="{{ request()->routeIs('admin.documents*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Documents</span>
                    </a>
                    @if(auth()->user()?->hasAnyRole(['super-admin', 'company-admin', 'branch-admin']))
                    <a href="{{ route('admin.approvals') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.approvals*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="approval" size="16" class="{{ request()->routeIs('admin.approvals*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Approvals</span>
                    </a>
                    @endif
                </div>

                {{-- Group 3: Printers & Agents --}}
                <div class="space-y-1">
                    <div class="px-3 pb-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Printers & Agents</div>
                    <a href="{{ route('admin.agents') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.agents*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="agent" size="16" class="{{ request()->routeIs('admin.agents*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Print Agents</span>
                    </a>
                    <a href="{{ route('admin.releases') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.releases*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="package" size="16" class="{{ request()->routeIs('admin.releases*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Agent Releases</span>
                    </a>
                    <a href="{{ route('admin.pools') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.pools*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="pool" size="16" class="{{ request()->routeIs('admin.pools*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Printer Pools</span>
                    </a>
                    <a href="{{ route('admin.printer-configs') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.printer-configs*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="sliders" size="16" class="{{ request()->routeIs('admin.printer-configs*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Printer Configs</span>
                    </a>
                </div>

                {{-- Group 4: Organization & Access --}}
                <div class="space-y-1">
                    <div class="px-3 pb-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Organization & Access</div>
                    @if(auth()->user()?->isSuperAdmin())
                    <a href="{{ route('admin.companies') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.companies*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="company" size="16" class="{{ request()->routeIs('admin.companies*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Companies</span>
                    </a>
                    @endif
                    @if(auth()->user()?->hasAnyRole(['super-admin', 'company-admin', 'branch-admin']))
                    <a href="{{ route('admin.branches') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.branches*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="branch" size="16" class="{{ request()->routeIs('admin.branches*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Branches</span>
                    </a>
                    @endif
                    @if(auth()->user()?->hasAnyRole(['super-admin', 'company-admin']))
                    <a href="{{ route('admin.users') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.users*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="users" size="16" class="{{ request()->routeIs('admin.users*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Users</span>
                    </a>
                    @endif
                    <a href="{{ route('admin.sessions') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.sessions*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="sessions" size="16" class="{{ request()->routeIs('admin.sessions*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Active Sessions</span>
                    </a>
                    @if(auth()->user()?->hasAnyRole(['super-admin', 'company-admin', 'branch-admin']))
                    <a href="{{ route('admin.activity-logs') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.activity-logs*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="activity" size="16" class="{{ request()->routeIs('admin.activity-logs*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Activity Logs</span>
                    </a>
                    @endif
                </div>

                {{-- Group 5: System & Integrations --}}
                <div class="space-y-1">
                    <div class="px-3 pb-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">System & Integrations</div>
                    @if(auth()->user()?->isSuperAdmin())
                    <a href="{{ route('admin.clients') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.clients*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="clients" size="16" class="{{ request()->routeIs('admin.clients*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Client Apps (API)</span>
                    </a>
                    <a href="{{ route('admin.settings') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.settings*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="settings" size="16" class="{{ request()->routeIs('admin.settings*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>System Settings</span>
                    </a>
                    <a href="{{ route('admin.webhooks.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.webhooks*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="webhook" size="16" class="{{ request()->routeIs('admin.webhooks*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Webhooks</span>
                    </a>
                    <a href="{{ route('admin.sso-settings') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.sso-settings*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="key" size="16" class="{{ request()->routeIs('admin.sso-settings*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>SSO Settings</span>
                    </a>
                    <a href="{{ route('admin.ip-whitelist') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.ip-whitelist*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="shield" size="16" class="{{ request()->routeIs('admin.ip-whitelist*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>IP Whitelist</span>
                    </a>
                    <a href="{{ route('admin.backup.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.backup*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="database" size="16" class="{{ request()->routeIs('admin.backup*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Backup & Restore</span>
                    </a>
                    <a href="{{ route('admin.mfa.setup') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.mfa*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="shield" size="16" class="{{ request()->routeIs('admin.mfa*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Two-Factor Auth</span>
                    </a>
                    @endif
                    <a href="{{ route('admin.scheduled-jobs.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.scheduled-jobs*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="clock" size="16" class="{{ request()->routeIs('admin.scheduled-jobs*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>Scheduled Jobs</span>
                    </a>
                    <a href="{{ route('admin.sdk-docs') }}#openapi" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('admin.sdk-docs*') ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <x-icon name="docs" size="16" class="{{ request()->routeIs('admin.sdk-docs*') ? 'text-white' : 'text-slate-400' }}" />
                        <span>API & SDK Docs</span>
                    </a>
                </div>
            </nav>

            <!-- Bottom Actions -->
            <div class="p-3 border-t border-slate-800 bg-slate-900/80 shrink-0 space-y-2">
                @auth
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold text-rose-500 hover:text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition">
                        <x-icon name="log-out" size="14" />
                        <span>Sign Out</span>
                    </button>
                </form>
                @endauth
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Header -->
            <header class="h-16 border-b border-slate-800 bg-slate-900/80 backdrop-blur-md px-6 flex items-center justify-between sticky top-0 z-40">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" aria-label="Toggle Navigation">
                        <x-icon name="menu" size="20" />
                    </button>
                    <h1 class="text-base font-bold tracking-tight text-white">@yield('title', 'Admin Portal')</h1>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Dark / Light Mode Toggle -->
                    <button onclick="toggleTheme()" class="p-2 rounded-xl border border-slate-700/80 bg-slate-800/80 hover:bg-slate-700 text-slate-200 transition flex items-center gap-1.5 text-xs font-semibold shadow-xs" title="Toggle Theme">
                        <span id="theme-icon-sun" class="hidden dark:inline"><x-icon name="sun" size="14" class="text-amber-400" /></span>
                        <span id="theme-icon-moon" class="inline dark:hidden"><x-icon name="moon" size="14" class="text-slate-700" /></span>
                        <span class="hidden sm:inline text-[11px] font-semibold" id="theme-text">Theme</span>
                    </button>

                    <!-- Status indicator -->
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden sm:inline">Hub Active</span>
                    </div>

                    <!-- Notification Bell -->
                    <a href="{{ route('admin.notifications') }}" class="p-2 rounded-xl border border-slate-700/80 bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition relative shadow-xs" title="Notifications">
                        <x-icon name="bell" size="15" />
                        <span id="notification-badge" class="hidden absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-full min-w-4 text-center"></span>
                    </a>

                    <!-- User Profile Avatar Pill -->
                    @auth
                    <div class="flex items-center gap-2.5 pl-3 border-l border-slate-800">
                        <a href="{{ route('admin.users') }}" class="flex items-center gap-2 hover:opacity-85 transition group" title="Account Management">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <div class="hidden md:block text-left">
                                <span class="block text-xs font-semibold leading-tight text-slate-200 group-hover:text-blue-400 transition">
                                    {{ auth()->user()->name }}
                                </span>
                                <span class="block text-[10px] text-slate-500 font-mono capitalize leading-tight">
                                    {{ str_replace('-', ' ', auth()->user()->role ?? 'Admin') }}
                                    @if(auth()->user()->branch)
                                        · {{ auth()->user()->branch->name }}
                                    @endif
                                </span>
                            </div>
                        </a>
                    </div>
                    @endauth
                </div>
            </header>

            <!-- Page Body -->
            <main class="flex-1 @if(!View::hasSection('fullwidth')) p-6 overflow-y-auto @else flex flex-col overflow-hidden p-0 m-0 @endif">
                <!-- Flash Messages -->
                @if($errors->any())
                    <div class="mb-5 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-500 dark:text-rose-400 text-xs font-semibold shadow-xs">
                        <div class="font-bold mb-1">Please correct the following errors:</div>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('success'))
                    <div class="mb-5 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between shadow-xs">
                        <span>✓ {!! session('success') !!}</span>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="mb-5 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs font-semibold flex items-center justify-between shadow-xs">
                        <span>⚠️ {!! session('warning') !!}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-5 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-semibold flex items-center justify-between shadow-xs">
                        <span>✕ {!! session('error') !!}</span>
                    </div>
                @endif

                @if(session('key_info'))
                    <div class="mb-5 p-4 rounded-2xl bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs font-mono flex items-center justify-between shadow-xs">
                        <span>🔑 {!! session('key_info') !!}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Floating Toast Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none" role="alert" aria-live="polite"></div>

    <script>
        // Sidebar drawer toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (!sidebar || !overlay) return;

            const isOpen = !sidebar.classList.contains('-translate-x-full');
            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            } else {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            }
        }

        // Theme Toggle (Dark / Light)
        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                localStorage.setItem('ph-theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                localStorage.setItem('ph-theme', 'dark');
            }
            updateThemeUI();
        }

        function updateThemeUI() {
            const isDark = document.documentElement.classList.contains('dark');
            const themeText = document.getElementById('theme-text');
            if (themeText) {
                themeText.textContent = isDark ? 'Dark' : 'Light';
            }
        }
        document.addEventListener('DOMContentLoaded', updateThemeUI);

        // Toast notifications
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-emerald-950/90 border-emerald-700 text-emerald-200' :
                            type === 'error' ? 'bg-rose-950/90 border-rose-700 text-rose-200' :
                            type === 'warning' ? 'bg-amber-950/90 border-amber-700 text-amber-200' :
                            'bg-slate-900/90 border-slate-700 text-slate-200';

            toast.className = `pointer-events-auto p-3.5 rounded-2xl border backdrop-blur-md shadow-xl text-xs font-semibold flex items-center justify-between gap-3 transition-all duration-300 opacity-0 translate-y-2 ${bgClass}`;
            toast.innerHTML = `<span class="flex-1">${message}</span><button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white">&times;</button>`;
            
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('opacity-0', 'translate-y-2');
            }, 10);

            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.add('opacity-0', 'translate-y-2');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
        }

        @if(session('toast_success'))
            showToast('{!! addslashes(session('toast_success')) !!}', 'success');
        @endif
        @if(session('toast_error'))
            showToast('{!! addslashes(session('toast_error')) !!}', 'error');
        @endif

        // Global Delete confirmation handlers
        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm(this.getAttribute('data-confirm') || 'Are you sure?')) {
                    e.preventDefault();
                }
            });
        });

        // Notifications Badge Polling
        (function() {
            const badge = document.getElementById('notification-badge');
            if (!badge) return;

            function updateBadge() {
                fetch('{{ route('admin.notifications.unread-count') }}')
                    .then(r => r.json())
                    .then(data => {
                        if (data.count > 0) {
                            badge.classList.remove('hidden');
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                        } else {
                            badge.classList.add('hidden');
                        }
                    })
                    .catch(() => {});
            }

            updateBadge();
            setInterval(updateBadge, 30000);
        })();
    </script>
</body>
</html>
