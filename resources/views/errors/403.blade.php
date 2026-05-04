@extends('admin.layout')
@section('title', '403 — Forbidden')

@section('content')
<div style="text-align: center; padding: 6rem 2rem;">
    <div style="font-size: 5rem; font-weight: 800; color: var(--danger); opacity: 0.2; line-height: 1;">403</div>
    <h1 style="font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.5rem; color: var(--danger);">Access Denied</h1>
    <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto 2rem;">
        You don't have permission to access this resource. Contact your administrator if you believe this is a mistake.
    </p>
    <a href="/" class="btn btn-primary" style="text-decoration: none;">Back to Dashboard</a>
</div>
@endsection
