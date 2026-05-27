@extends('admin.layout')
@section('title', 'Agent Releases')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Agent Releases']]" />

<div class="page-header">
    <h1>Agent Releases</h1>
    <p>Manage auto-update releases for Print Agents across platforms</p>
</div>

{{-- Upload New Release Form --}}
<div class="card">
    <div class="card-header">
        <h2>Upload New Release</h2>
    </div>
    <form action="{{ route('admin.releases.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr 1fr;">
            <div class="form-group">
                <label for="version">Version <span style="color: var(--danger);">*</span></label>
                <input type="text" name="version" id="version" required placeholder="e.g. 3.1.0"
                       value="{{ old('version') }}">
                @error('version') <small style="color: var(--danger);">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label for="platform">Platform <span style="color: var(--danger);">*</span></label>
                <select name="platform" id="platform" required>
                    <option value="">-- Select --</option>
                    <option value="linux" @selected(old('platform') === 'linux')>Linux</option>
                    <option value="windows" @selected(old('platform') === 'windows')>Windows</option>
                    <option value="macos" @selected(old('platform') === 'macos')>macOS</option>
                </select>
                @error('platform') <small style="color: var(--danger);">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label for="channel">Channel <span style="color: var(--danger);">*</span></label>
                <select name="channel" id="channel" required>
                    <option value="">-- Select --</option>
                    <option value="stable" @selected(old('channel') === 'stable')>Stable</option>
                    <option value="beta" @selected(old('channel') === 'beta')>Beta</option>
                    <option value="alpha" @selected(old('channel') === 'alpha')>Alpha</option>
                </select>
                @error('channel') <small style="color: var(--danger);">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label for="is_mandatory">Mandatory Update</label>
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                    <input type="checkbox" name="is_mandatory" id="is_mandatory" value="1" @checked(old('is_mandatory'))>
                    <span style="font-weight: 400;">Force update on next check</span>
                </label>
            </div>
        </div>

        <div class="form-row" style="grid-template-columns: 2fr 1fr;">
            <div class="form-group">
                <label for="release_notes">Release Notes</label>
                <textarea name="release_notes" id="release_notes" rows="3"
                          placeholder="Describe what's new in this release...">{{ old('release_notes') }}</textarea>
                @error('release_notes') <small style="color: var(--danger);">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label for="installer_file">Installer File <span style="color: var(--danger);">*</span></label>
                <input type="file" name="installer_file" id="installer_file" required accept=".exe,.msi,.deb,.rpm,.AppImage,.dmg,.pkg,.tar.gz,.zip">
                <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                    Max 500MB. Accepted formats: .exe, .msi, .deb, .rpm, .AppImage, .dmg, .pkg, .tar.gz, .zip
                </small>
                @error('installer_file') <small style="color: var(--danger);">{{ $message }}</small> @enderror
            </div>
        </div>

        <button type="submit" class="btn btn-primary">📦 Upload Release</button>
    </form>
</div>

{{-- Releases List --}}
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Releases ({{ $releases->count() }})</h2>
    </div>

    @if($releases->isEmpty())
        <x-empty-state icon="📦" title="No releases yet"
            description="Upload your first agent installer above." />
    @else
        <table role="table">
            <caption class="sr-only">Agent releases list</caption>
            <thead>
                <tr>
                    <th scope="col">Version</th>
                    <th scope="col">Platform</th>
                    <th scope="col">Channel</th>
                    <th scope="col">File</th>
                    <th scope="col">Size</th>
                    <th scope="col">SHA-256</th>
                    <th scope="col">Mandatory</th>
                    <th scope="col">Latest</th>
                    <th scope="col">Uploaded By</th>
                    <th scope="col">Date</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($releases as $release)
                <tr>
                    <td><strong>{{ $release->version }}</strong></td>
                    <td>
                        @switch($release->platform)
                            @case('linux') 🐧 Linux @break
                            @case('windows') 🪟 Windows @break
                            @case('macos') 🍏 macOS @break
                            @default {{ $release->platform }}
                        @endswitch
                    </td>
                    <td>
                        <span style="
                            display: inline-block;
                            padding: 2px 8px;
                            border-radius: 10px;
                            font-size: 0.75rem;
                            font-weight: 600;
                            background: @switch($release->channel)
                                @case('stable') var(--success) @break
                                @case('beta') var(--warning) @break
                                @case('alpha') var(--danger) @break
                                @default var(--text-muted)
                            @endswitch;
                            color: #000;
                        ">{{ ucfirst($release->channel) }}</span>
                    </td>
                    <td>
                        <span title="{{ $release->file_original_name }}">
                            {{ \Illuminate\Support\Str::limit($release->file_original_name, 25) }}
                        </span>
                    </td>
                    <td>{{ $release->formatted_size }}</td>
                    <td>
                        <code style="font-size: 0.7rem; background: var(--bg); padding: 2px 6px; border-radius: 4px; cursor: help;"
                              title="{{ $release->sha256_hash }}">
                            {{ \Illuminate\Support\Str::limit($release->sha256_hash, 16) }}
                        </code>
                    </td>
                    <td>
                        @if($release->is_mandatory)
                            <span style="color: var(--warning); font-weight: 600;">⚠ Yes</span>
                        @else
                            <span style="color: var(--text-muted);">—</span>
                        @endif
                    </td>
                    <td>
                        @if($release->is_latest)
                            <span style="
                                display: inline-block;
                                padding: 2px 8px;
                                border-radius: 10px;
                                font-size: 0.7rem;
                                font-weight: 700;
                                background: var(--primary);
                                color: #fff;
                            ">LATEST</span>
                        @else
                            <span style="color: var(--text-muted);">—</span>
                        @endif
                    </td>
                    <td>
                        @if($release->uploader)
                            {{ $release->uploader->name }}
                        @else
                            <span style="color: var(--text-muted);">—</span>
                        @endif
                    </td>
                    <td style="white-space: nowrap; font-size: 0.8rem;">
                        {{ $release->created_at->format('Y-m-d') }}
                        <br><small style="color: var(--text-muted);">{{ $release->created_at->format('H:i') }}</small>
                    </td>
                    <td>
                        <div style="display: flex; gap: 4px; flex-wrap: nowrap;">
                            @if(!$release->is_latest)
                            <form action="{{ route('admin.releases.mark-latest', $release) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary"
                                        title="Mark as latest for {{ $release->platform }}"
                                        onclick="return confirm('Set {{ $release->version }} as the latest {{ $release->platform }} release?')">
                                    ★
                                </button>
                            </form>
                            @endif
                            <form action="{{ route('admin.releases.destroy', $release) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger"
                                        title="Delete this release"
                                        onclick="return confirm('Delete release {{ $release->version }} for {{ $release->platform }}? This cannot be undone.')">
                                    🗑
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<style>
    .form-row {
        display: grid;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 0.35rem;
        color: var(--text-muted);
    }

    .form-group input[type="text"],
    .form-group input[type="file"],
    .form-group select,
    .form-group textarea {
        padding: 8px 12px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text);
        font-size: 0.85rem;
        outline: none;
        transition: border-color 0.15s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: var(--primary);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 60px;
        font-family: inherit;
    }

    .form-group input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
    }

    .btn-sm {
        padding: 4px 10px;
        font-size: 0.8rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: opacity 0.15s;
    }

    .btn-sm:hover {
        opacity: 0.8;
    }

    .btn-primary {
        background: var(--primary);
        color: #fff;
    }

    .btn-danger {
        background: transparent;
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .btn-danger:hover {
        background: var(--danger);
        color: #fff;
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }
</style>
@endsection
