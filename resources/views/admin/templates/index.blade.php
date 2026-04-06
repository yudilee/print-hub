@extends('admin.layout')
@section('title', 'Templates')

@section('content')
<div class="page-header">
    <h1>Print Templates</h1>
    <p>Design millimeter-perfect layouts for pre-printed continuous forms</p>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Templates ({{ $templates->count() }})</h2>
        <a href="{{ route('admin.templates.create') }}" class="btn btn-primary">+ Create New Template</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Template Name</th>
                <th>Dimensions (mm)</th>
                <th>Elements</th>
                <th>Last Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($templates as $template)
            <tr>
                <td><strong>{{ $template->name }}</strong></td>
                <td><span class="badge badge-info">{{ $template->paper_width_mm }} x {{ $template->paper_height_mm }}</span></td>
                <td style="color: var(--text-muted);">{{ count($template->elements ?? []) }} element(s)</td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $template->updated_at->diffForHumans() }}</td>
                <td style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-secondary btn-sm">Edit Designer</a>
                    <form action="{{ route('admin.templates.clone', $template) }}" method="POST">
                        @csrf
                        <button class="btn btn-secondary btn-sm" title="Duplicate this template">Clone</button>
                    </form>
                    <form action="{{ route('admin.templates.destroy', $template) }}" method="POST" onsubmit="return confirm('Delete this template?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="color: var(--text-muted); text-align: center; padding: 2rem;">No templates designed yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-header"><h2>Managed Print API Usage</h2></div>
    <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.6;">
        Instead of generating PDFs in your web app, you can now send raw JSON data to the Hub and reference a template:
    </p>
    <pre style="background: var(--bg); padding: 1rem; border-radius: 6px; margin-top: 0.75rem; font-size: 0.8rem; overflow-x: auto; color: var(--text);">// POST /api/v1/jobs
{
    "agent_id": 1,
    "printer": "Brother-HL-L2360D",
    "template": "invoice_v1",  // ← Template name
    "template_data": {         // ← Raw data for positioning
        "customer_name": "John Doe",
        "invoice_no": "INV-1002",
        "items": [
            { "name": "Item A", "qty": 2, "price": "10.00" },
            { "name": "Item B", "qty": 1, "price": "25.00" }
        ]
    }
}</pre>
</div>
@endsection
