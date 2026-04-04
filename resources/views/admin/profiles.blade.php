@extends('admin.layout')
@section('title', 'Profiles')

@section('content')
<div class="page-header">
    <h1>Print Profiles</h1>
    <p>Define reusable print configurations that are synced to all agents</p>
</div>

{{-- Create Profile --}}
<div class="card">
    <div class="card-header"><h2>Create New Profile</h2></div>
    <form action="{{ route('admin.profiles.store') }}" method="POST">
        @csrf
        <div class="form-row">
            <div class="form-group">
                <label for="name">Profile Name (e.g. invoice_sewa)</label>
                <input type="text" name="name" id="name" required placeholder="unique_profile_name">
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" name="description" id="description" placeholder="e.g. Invoice Sewa A4 Portrait">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="paper_size">Paper Size</label>
                <select name="paper_size" id="paper_size">
                    <option value="A4" selected>A4</option>
                    <option value="A5">A5</option>
                    <option value="Letter">Letter</option>
                    <option value="Legal">Legal</option>
                    <option value="F4">F4 / Folio</option>
                </select>
            </div>
            <div class="form-group">
                <label for="orientation">Orientation</label>
                <select name="orientation" id="orientation">
                    <option value="portrait" selected>Portrait</option>
                    <option value="landscape">Landscape</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="copies">Copies</label>
                <input type="number" name="copies" id="copies" value="1" min="1" max="99">
            </div>
            <div class="form-group">
                <label for="duplex">Duplex</label>
                <select name="duplex" id="duplex">
                    <option value="one-sided" selected>One-sided</option>
                    <option value="two-sided-long">Two-sided (Long edge)</option>
                    <option value="two-sided-short">Two-sided (Short edge)</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="default_printer">Default Printer Name (optional — leave blank to use agent's default)</label>
            <input type="text" name="default_printer" id="default_printer" placeholder="e.g. Brother-HL-L2360D">
        </div>
        <button type="submit" class="btn btn-primary">+ Create Profile</button>
    </form>
</div>

{{-- Profile List --}}
<div class="card">
    <div class="card-header"><h2>All Profiles ({{ $profiles->count() }})</h2></div>
    <table>
        <thead>
            <tr>
                <th>Profile Name</th>
                <th>Description</th>
                <th>Paper</th>
                <th>Orient.</th>
                <th>Copies</th>
                <th>Duplex</th>
                <th>Default Printer</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($profiles as $profile)
            <tr>
                <td><code class="mono">{{ $profile->name }}</code></td>
                <td style="color: var(--text-muted);">{{ $profile->description ?? '—' }}</td>
                <td><span class="badge badge-info">{{ $profile->paper_size }}</span></td>
                <td>{{ ucfirst($profile->orientation) }}</td>
                <td>{{ $profile->copies }}</td>
                <td style="font-size: 0.8rem;">{{ $profile->duplex }}</td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $profile->default_printer ?? '(agent default)' }}</td>
                <td>
                    <form action="{{ route('admin.profiles.destroy', $profile) }}" method="POST" onsubmit="return confirm('Delete this profile?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="color: var(--text-muted);">No profiles created yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($profiles->count() > 0)
<div class="card">
    <div class="card-header"><h2>Usage in Laravel Frontend</h2></div>
    <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.6;">
        When sending a print job from your web app, reference the profile name:
    </p>
    <pre style="background: var(--bg); padding: 1rem; border-radius: 6px; margin-top: 0.75rem; font-size: 0.8rem; overflow-x: auto; color: var(--text);">fetch('http://127.0.0.1:49211/print', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        type: 'pdf',
        profile: '<span style="color: var(--warning);">{{ $profiles->first()->name }}</span>',
        data: pdfBase64
    })
});</pre>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.75rem;">
        The tray agent will automatically resolve the printer, paper size, orientation, copies, and duplex from this profile.
    </p>
</div>
@endif
@endsection
