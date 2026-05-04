<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Hub</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; padding: 2rem; max-width: 500px; }
        .logo { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { color: #94a3b8; margin-bottom: 2rem; line-height: 1.6; }
        .btn { display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; transition: background 0.2s; }
        .btn:hover { background: #2563eb; }
        .features { display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
        .feature { background: #1e293b; padding: 1rem; border-radius: 8px; border: 1px solid #334155; width: 140px; }
        .feature .icon { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .feature span { font-size: 0.8rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🖨️</div>
        <h1>Print Hub</h1>
        <p>Centralized print management for multi-branch organizations. Design templates, route jobs to agents, and monitor print status — all in one place.</p>
        <a href="/login" class="btn">Sign In</a>
        <div class="features">
            <div class="feature"><div class="icon">📋</div><span>Template Designer</span></div>
            <div class="feature"><div class="icon">🖨️</div><span>Agent Routing</span></div>
            <div class="feature"><div class="icon">📊</div><span>Job Monitoring</span></div>
            <div class="feature"><div class="icon">🔌</div><span>API & SDK</span></div>
        </div>
    </div>
</body>
</html>
