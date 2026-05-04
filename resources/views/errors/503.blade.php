@extends('admin.layout')
@section('title', '503 — Service Unavailable')

@section('content')
<div style="text-align: center; padding: 6rem 2rem;">
    <div style="font-size: 5rem; font-weight: 800; color: var(--warning); opacity: 0.2; line-height: 1;">503</div>
    <h1 style="font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.5rem; color: var(--warning);">Service Unavailable</h1>
    <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto 2rem;">
        The print hub is currently unable to handle the request. This could be due to maintenance or high load. Please try again in a moment.
    </p>
    <a href="/" class="btn btn-primary" style="text-decoration: none;">Back to Dashboard</a>
</div>
@endsection
