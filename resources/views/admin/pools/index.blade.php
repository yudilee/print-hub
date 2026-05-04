@extends('admin.layout')
@section('title', 'Printer Pools')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>🔄 Printer Pools</h1>
            <p>Group printers into pools for load-balanced job distribution.</p>
        </div>
        <a href="{{ route('admin.pools.create') }}" class="btn btn-primary">+ New Pool</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">✓ {{ session('success') }}</div>
@endif

@if($pools->count() > 0)
    <div style="display: grid; gap: 1rem;">
        @foreach($pools as $pool)
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <h3 style="font-size: 1rem; font-weight: 600;">{{ $pool->name }}</h3>
                        @if($pool->active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                        <span class="badge badge-info">{{ $pool->strategy }}</span>
                    </div>
                    @if($pool->description)
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.75rem;">{{ $pool->description }}</p>
                    @endif
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('admin.pools.edit', $pool) }}" class="btn btn-secondary btn-sm">Edit</a>
                    <form action="{{ route('admin.pools.destroy', $pool) }}" method="POST" data-confirm="Delete this pool?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>

            {{-- Printers in this pool --}}
            @php $poolPrinters = $pool->printers; @endphp
            @if($poolPrinters->count() > 0)
            <div style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.75rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                    Printers ({{ $poolPrinters->count() }})
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @foreach($poolPrinters->sortBy('priority') as $pp)
                    <div style="background: var(--bg); padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid var(--border); font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem;">
                        @if(!$pp->active)
                            <span style="color: var(--danger);">⊘</span>
                        @endif
                        <span>{{ $pp->printer_name }}</span>
                        <span style="color: var(--text-muted); font-size: 0.7rem;">(priority {{ $pp->priority }})</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <div style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.75rem;">
                <span style="color: var(--text-muted); font-size: 0.8rem;">No printers assigned yet.</span>
            </div>
            @endif
        </div>
        @endforeach
    </div>
@else
    <x-empty-state icon="printer" title="No printer pools" message="Create your first pool to group printers for load-balanced job distribution.">
        <a href="{{ route('admin.pools.create') }}" class="btn btn-primary">+ New Pool</a>
    </x-empty-state>
@endif
@endsection
