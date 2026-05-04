@extends('admin.layout')
@section('title', '500 — Server Error')

@section('content')
<div style="text-align: center; padding: 6rem 2rem;">
    <div style="font-size: 5rem; font-weight: 800; color: var(--danger); opacity: 0.2; line-height: 1;">500</div>
    <h1 style="font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.5rem; color: var(--danger);">Internal Server Error</h1>
    <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto 2rem;">
        Something went wrong on our end. Please try again later or contact support if the issue persists.
    </p>
    <a href="/" class="btn btn-primary" style="text-decoration: none;">Back to Dashboard</a>
</div>
@endsection
