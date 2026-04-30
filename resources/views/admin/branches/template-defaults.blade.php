@extends('admin.layout')
@section('title', 'Template Defaults — ' . $branch->name)

@section('content')
<div class="page-header">
    <h1>Template Defaults: {{ $branch->name }}</h1>
    <p>Assign a default queue (agent + printer) for each template when printing from this branch.</p>
</div>

<div style="margin-bottom: 1.5rem;">
    <a href="{{ route('admin.branches') }}" class="btn btn-secondary" style="text-decoration: none;">← Back to Branches</a>
</div>

{{-- Branch Info --}}
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary);">{{ $branch->code }}</div>
        <div class="stat-label">Branch Code</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info);">{{ $profiles->count() }}</div>
        <div class="stat-label">Available Queues</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success);">{{ $defaults->count() }}</div>
        <div class="stat-label">Configured Defaults</div>
    </div>
</div>

@if($profiles->isEmpty())
<div class="card" style="text-align: center; padding: 3rem;">
    <p style="color: var(--warning); font-size: 1.1rem; margin-bottom: 0.5rem;">⚠️ No queues assigned to this branch yet</p>
    <p style="color: var(--text-muted);">Create queues in the <a href="{{ route('admin.profiles') }}" style="color: var(--primary);">Queues</a> page and assign them to this branch first.</p>
</div>
@else
<div class="card">
    <div class="card-header">
        <h2>Template → Queue Mapping</h2>
    </div>
    <form action="{{ route('admin.branches.template-defaults.save', $branch) }}" method="POST">
        @csrf
        <table>
            <thead>
                <tr>
                    <th>Template</th>
                    <th>Default Queue</th>
                    <th>Agent → Printer</th>
                </tr>
            </thead>
            <tbody>
                @foreach($templates as $template)
                <tr>
                    <td>
                        <strong>{{ $template->name }}</strong>
                        <div style="font-size: 0.7rem; color: var(--text-muted);">
                            {{ $template->paper_width_mm }}×{{ $template->paper_height_mm }}mm
                        </div>
                    </td>
                    <td>
                        <input type="hidden" name="defaults[{{ $loop->index }}][template_id]" value="{{ $template->id }}">
                        <select name="defaults[{{ $loop->index }}][profile_id]" onchange="updatePreview(this, {{ $loop->index }})" style="min-width: 200px;">
                            <option value="">— No Default —</option>
                            @foreach($profiles as $profile)
                                <option value="{{ $profile->id }}"
                                    data-agent="{{ $profile->agent?->name ?? 'No Agent' }}"
                                    data-printer="{{ $profile->default_printer ?? 'OS Default' }}"
                                    data-online="{{ $profile->agent?->isOnline() ? '1' : '0' }}"
                                    {{ ($defaults[$template->id]?->print_profile_id ?? '') == $profile->id ? 'selected' : '' }}>
                                    {{ $profile->name }} ({{ $profile->paper_size }})
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <span id="preview-{{ $loop->index }}" style="font-size: 0.8rem; color: var(--text-muted);">
                            @if(isset($defaults[$template->id]))
                                @php $def = $defaults[$template->id]; @endphp
                                <span style="color: {{ $def->profile?->agent?->isOnline() ? 'var(--success)' : 'var(--danger)' }};">●</span>
                                {{ $def->profile?->agent?->name ?? '?' }} → <code>{{ $def->profile?->default_printer ?? 'OS Default' }}</code>
                            @else
                                <span style="font-style: italic;">Not configured</span>
                            @endif
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary">💾 Save Template Defaults</button>
        </div>
    </form>
</div>
@endif

<script>
function updatePreview(select, idx) {
    const preview = document.getElementById(`preview-${idx}`);
    const option = select.options[select.selectedIndex];
    
    if (!option.value) {
        preview.innerHTML = '<span style="font-style: italic;">Not configured</span>';
        return;
    }
    
    const agent = option.dataset.agent;
    const printer = option.dataset.printer;
    const online = option.dataset.online === '1';
    const dotColor = online ? 'var(--success)' : 'var(--danger)';
    
    preview.innerHTML = `<span style="color: ${dotColor};">●</span> ${agent} → <code>${printer}</code>`;
}
</script>
@endsection
