@extends('admin.layout')
@section('title', $pool->exists ? 'Edit Pool: ' . $pool->name : 'New Printer Pool')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>{{ $pool->exists ? 'Edit Pool: ' . $pool->name : 'New Printer Pool' }}</h1>
            <p>{{ $pool->exists ? 'Modify the pool configuration.' : 'Create a new printer pool for load-balanced distribution.' }}</p>
        </div>
        <a href="{{ route('admin.pools') }}" class="btn btn-secondary">← Back to Pools</a>
    </div>
</div>

<div class="card">
    <form action="{{ $pool->exists ? route('admin.pools.update', $pool) : route('admin.pools.store') }}" method="POST">
        @csrf
        @if($pool->exists)
            @method('PUT')
        @endif

        @if($errors->any())
            <div style="background: rgba(255, 50, 50, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <ul style="margin: 0; padding-left: 1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Pool Details --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem;">
                📋 Pool Details
            </legend>

            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="name">Pool Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $pool->name) }}" required placeholder="e.g. Invoice Printers">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="strategy">Distribution Strategy</label>
                    <select name="strategy" id="strategy">
                        <option value="round_robin" {{ old('strategy', $pool->strategy) == 'round_robin' ? 'selected' : '' }}>Round Robin</option>
                        <option value="least_busy" {{ old('strategy', $pool->strategy) == 'least_busy' ? 'selected' : '' }}>Least Busy</option>
                        <option value="random" {{ old('strategy', $pool->strategy) == 'random' ? 'selected' : '' }}>Random</option>
                        <option value="failover" {{ old('strategy', $pool->strategy) == 'failover' ? 'selected' : '' }}>Failover</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" {{ old('active', $pool->active ?? true) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        Active
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="2" placeholder="What is this pool for?">{{ old('description', $pool->description) }}</textarea>
            </div>
        </fieldset>

        {{-- Printers in Pool --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem;">
                🖨️ Printers in Pool
            </legend>

            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1rem;">
                Add printers to this pool. The strategy determines how jobs are distributed.
            </p>

            <div id="printers-container">
                @php
                    $existingPrinters = $pool->exists ? $pool->printers : old('printers', []);
                @endphp

                @if(count($existingPrinters) > 0)
                    @foreach($existingPrinters as $idx => $pp)
                        @php $ppData = $pp instanceof \App\Models\PrinterPoolPrinter ? $pp : (object) $pp; @endphp
                        <div class="printer-row" style="display: flex; gap: 0.75rem; align-items: flex-end; margin-bottom: 0.75rem; padding: 0.75rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border);">
                            <div class="form-group" style="flex: 3; margin-bottom: 0;">
                                <label>Printer Name</label>
                                <input type="text" name="printers[{{ $idx }}][name]" value="{{ old('printers.' . $idx . '.name', $ppData->printer_name) }}" required placeholder="e.g. Printer-01" list="printer-list">
                            </div>
                            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                <label>Priority</label>
                                <input type="number" name="printers[{{ $idx }}][priority]" value="{{ old('printers.' . $idx . '.priority', $ppData->priority ?? $idx) }}" min="0" placeholder="0">
                            </div>
                            <div class="form-group" style="flex: 1; display: flex; align-items: center; padding-bottom: 0.5rem; margin-bottom: 0;">
                                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="printers[{{ $idx }}][active]" value="1" {{ old('printers.' . $idx . '.active', $ppData->active ?? true) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                                    Active
                                </label>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.printer-row').remove()" style="margin-bottom: 0.5rem;">✕</button>
                        </div>
                    @endforeach
                @endif
            </div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="addPrinterRow()">+ Add Printer</button>

            <datalist id="printer-list">
                @foreach($allPrinters as $printerName)
                    <option value="{{ $printerName }}">
                @endforeach
            </datalist>
        </fieldset>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">{{ $pool->exists ? 'Update Pool' : 'Create Pool' }}</button>
            <a href="{{ route('admin.pools') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
let printerIdx = {{ count($existingPrinters) }};

function addPrinterRow() {
    const container = document.getElementById('printers-container');
    const idx = printerIdx++;
    const html = `
        <div class="printer-row" style="display: flex; gap: 0.75rem; align-items: flex-end; margin-bottom: 0.75rem; padding: 0.75rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border);">
            <div class="form-group" style="flex: 3; margin-bottom: 0;">
                <label>Printer Name</label>
                <input type="text" name="printers[${idx}][name]" required placeholder="e.g. Printer-01" list="printer-list">
            </div>
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label>Priority</label>
                <input type="number" name="printers[${idx}][priority]" value="${idx}" min="0" placeholder="0">
            </div>
            <div class="form-group" style="flex: 1; display: flex; align-items: center; padding-bottom: 0.5rem; margin-bottom: 0;">
                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="printers[${idx}][active]" value="1" checked style="width: 18px; height: 18px;">
                    Active
                </label>
            </div>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.printer-row').remove()" style="margin-bottom: 0.5rem;">✕</button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}
</script>
@endsection
