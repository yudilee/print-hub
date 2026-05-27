@extends('admin.layout')
@section('title', 'Schedule New Job')

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Scheduled Jobs', 'url' => route('admin.scheduled-jobs.index')],
    ['label' => 'Schedule Job'],
]" />

<div class="page-header">
    <h1>Schedule New Print Job</h1>
    <p>Create a print job to run at a specific time or on a recurring schedule.</p>
</div>

<div class="card">
    <div class="card-header"><h2>Job Details</h2></div>
    <form action="{{ route('admin.scheduled-jobs.store') }}" method="POST" data-loading>
        @csrf

        <div class="form-group">
            <label for="print_profile_id">Print Profile (Queue) *</label>
            <select name="print_profile_id" id="print_profile_id" required>
                <option value="">-- Select Print Profile --</option>
                @foreach($profiles as $profile)
                    <option value="{{ $profile->id }}" {{ old('print_profile_id') == $profile->id ? 'selected' : '' }}>
                        {{ $profile->name }}
                        @if($profile->agent)
                            — {{ $profile->agent->name }}
                            @if($profile->branch)
                                ({{ $profile->branch->name }})
                            @endif
                        @endif
                    </option>
                @endforeach
            </select>
            @error('print_profile_id')
                <div class="field-error visible">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="template_name">Template Name (optional)</label>
                <input type="text" name="template_name" id="template_name" value="{{ old('template_name') }}"
                       placeholder="e.g. invoice_sewa">
                @error('template_name')
                    <div class="field-error visible">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="reference_id">Reference ID (optional)</label>
                <input type="text" name="reference_id" id="reference_id" value="{{ old('reference_id') }}"
                       placeholder="Your tracking reference">
                @error('reference_id')
                    <div class="field-error visible">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="scheduled_at">Scheduled Date & Time *</label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                       value="{{ old('scheduled_at') }}" required
                       min="{{ now()->format('Y-m-d\TH:i') }}">
                @error('scheduled_at')
                    <div class="field-error visible">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select name="priority" id="priority">
                    <option value="1" {{ old('priority') == 1 ? 'selected' : '' }}>🔴 Low (1)</option>
                    <option value="2" {{ old('priority', 2) == 2 ? 'selected' : '' }} selected>🟡 Normal (2)</option>
                    <option value="3" {{ old('priority') == 3 ? 'selected' : '' }}>🟠 High (3)</option>
                    <option value="4" {{ old('priority') == 4 ? 'selected' : '' }}>🔴 Urgent (4)</option>
                </select>
                @error('priority')
                    <div class="field-error visible">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card" style="margin-top: 1rem;">
            <div class="card-header"><h2>Recurrence Settings</h2></div>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
                Set up recurring printing if this job should repeat automatically.
            </p>

            <div class="form-row">
                <div class="form-group">
                    <label for="recurrence">Recurrence</label>
                    <select name="recurrence" id="recurrence">
                        <option value="none" {{ old('recurrence', 'none') === 'none' ? 'selected' : '' }}>None (One-Time)</option>
                        <option value="daily" {{ old('recurrence') === 'daily' ? 'selected' : '' }}>🔄 Daily</option>
                        <option value="weekly" {{ old('recurrence') === 'weekly' ? 'selected' : '' }}>🔄 Weekly</option>
                        <option value="monthly" {{ old('recurrence') === 'monthly' ? 'selected' : '' }}>🔄 Monthly</option>
                    </select>
                    @error('recurrence')
                        <div class="field-error visible">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="recurrence_count">Max Occurrences (optional)</label>
                    <input type="number" name="recurrence_count" id="recurrence_count"
                           value="{{ old('recurrence_count') }}" min="1" max="999"
                           placeholder="Leave empty for unlimited">
                    @error('recurrence_count')
                        <div class="field-error visible">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="recurrence_end_at">End Date (optional)</label>
                    <input type="datetime-local" name="recurrence_end_at" id="recurrence_end_at"
                           value="{{ old('recurrence_end_at') }}"
                           placeholder="When to stop recurring">
                    @error('recurrence_end_at')
                        <div class="field-error visible">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    {{-- Empty for spacing --}}
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Schedule Job</button>
            <a href="{{ route('admin.scheduled-jobs.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum datetime to now for the scheduled_at field
    const scheduledAt = document.getElementById('scheduled_at');
    if (scheduledAt) {
        const now = new Date();
        const localStr = now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0') + 'T' +
            String(now.getHours()).padStart(2, '0') + ':' +
            String(now.getMinutes()).padStart(2, '0');
        scheduledAt.setAttribute('min', localStr);
    }
});
</script>
@endsection
