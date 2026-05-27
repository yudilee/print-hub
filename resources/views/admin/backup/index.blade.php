@extends('admin.layout')
@section('title', 'Backup & Restore')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Backup & Restore']]" />

<div class="page-header">
    <h1>Backup & Restore</h1>
    <p>Export and import Print Hub configuration (agents, profiles, templates, fonts, pools, settings, releases).</p>
</div>

{{-- Export Section --}}
<div class="card">
    <div class="card-header">
        <h2>📤 Export Configuration</h2>
    </div>
    <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem;">
        Export all configuration data as a JSON file. This includes agents, print profiles,
        templates (metadata only), fonts (metadata only), printer pools, printer configs,
        system settings, and agent releases.
    </p>
    <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.8rem; color: var(--warning);">
        ⚠️ Note: Template background images and font file data are excluded from the export.
        You will need to re-upload these after import.
    </div>
    <form action="{{ route('admin.backup.export') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary">⬇ Export Configuration</button>
    </form>
</div>

{{-- Import Section --}}
<div class="card">
    <div class="card-header">
        <h2>📥 Import Configuration</h2>
    </div>
    <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem;">
        Import configuration from a previously exported JSON file. Existing records will be
        updated, and new records will be created. This operation is transactional — if any
        part fails, all changes are rolled back.
    </p>
    <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.8rem; color: var(--danger);">
        ⚠️ Warning: Importing will overwrite existing records with the same IDs.
        Make sure to export your current configuration before importing.
    </div>

    <form action="{{ route('admin.backup.import') }}" method="POST" enctype="multipart/form-data" data-loading>
        @csrf
        <div class="form-group">
            <label for="backup_file">Select Backup JSON File</label>
            <input type="file" name="backup_file" id="backup_file" accept=".json" required>
            @error('backup_file')
                <div class="field-error visible">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-warning" data-loading-text="Importing...">📥 Import Configuration</button>
    </form>
</div>

{{-- Existing Backups --}}
<div class="card">
    <div class="card-header">
        <h2>💾 Existing Backup Files</h2>
    </div>
    @if(count($backupFiles) > 0)
        <table role="table">
            <thead>
                <tr>
                    <th scope="col">Filename</th>
                    <th scope="col">Size</th>
                    <th scope="col">Modified</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($backupFiles as $file)
                <tr>
                    <td><code class="mono">{{ $file['name'] }}</code></td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $file['size'] }}</td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $file['modified'] }}</td>
                    <td>
                        <div style="display: flex; gap: 4px;">
                            <a href="{{ route('admin.backup.download', basename($file['name'])) }}" class="btn btn-primary btn-sm">⬇ Download</a>
                            <form action="{{ route('admin.backup.delete', basename($file['name'])) }}" method="POST"
                                  onsubmit="return confirm('Delete this backup file?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.85rem;">
            No backup files found. Export your configuration to create one.
        </div>
    @endif
</div>
@endsection
