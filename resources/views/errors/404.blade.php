@extends('admin.layout')
@section('title', '404 — Not Found')

@section('content')
<div style="text-align: center; padding: 6rem 2rem;">
    <div style="font-size: 5rem; font-weight: 800; color: var(--text-muted); opacity: 0.2; line-height: 1;">404</div>
    <h1 style="font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.5rem;">Page Not Found</h1>
    <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto 2rem;">
        The page you are looking for doesn't exist or has been moved.
    </p>
    <a href="/" class="btn btn-primary" style="text-decoration: none;">Back to Dashboard</a>
</div>
@endsection
