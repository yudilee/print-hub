<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login') — Print Hub</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root, [data-theme="dark"] {
            --bg: #0f1117; --surface: #1a1d27; --border: #2a2e3f;
            --text: #e4e6ed; --text-muted: #8b8fa3; --primary: #6366f1;
            --primary-hover: #818cf8; --danger: #ef4444; --success: #22c55e;
        }
        [data-theme="light"] {
            --bg: #f8fafc; --surface: #ffffff; --border: #e2e8f0;
            --text: #0f172a; --text-muted: #64748b; --primary: #6366f1;
            --primary-hover: #4f46e5; --danger: #dc2626; --success: #16a34a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .login-container {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; width: 100%; max-width: 420px;
            padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header h1 {
            font-size: 1.5rem; font-weight: 700;
            background: linear-gradient(135deg, var(--primary), #a855f7);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .login-header p { color: var(--text-muted); font-size: 0.875rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-muted); margin-bottom: 0.5rem; }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%; padding: 0.75rem 1rem; background: var(--bg);
            border: 1px solid var(--border); border-radius: 8px; color: var(--text);
            font-size: 0.9rem; font-family: inherit; transition: border-color 0.15s;
        }
        input:focus { outline: none; border-color: var(--primary); }
        .btn-primary {
            width: 100%; padding: 0.75rem; background: var(--primary); color: white;
            border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600;
            cursor: pointer; transition: background 0.15s; margin-top: 0.5rem;
        }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary {
            width: 100%; padding: 0.75rem; background: transparent; color: var(--text-muted);
            border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem;
            cursor: pointer; margin-top: 0.5rem; text-decoration: none; display: block; text-align: center;
        }
        .btn-secondary:hover { color: var(--text); border-color: var(--text-muted); }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1); color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.5rem;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1); color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
            padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.5rem;
        }
        .forgot-link {
            display: block; text-align: right; font-size: 0.8rem; color: var(--text-muted);
            text-decoration: none; margin-top: -0.5rem; margin-bottom: 0.5rem;
        }
        .forgot-link:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="login-container">
        @yield('content')
    </div>
</body>
</html>
