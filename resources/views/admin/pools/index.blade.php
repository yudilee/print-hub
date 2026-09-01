@extends('admin.layout')
@section('title', 'Printer Pools')

@section('content')
<x-breadcrumb :items="[['label' => 'Printer Pools']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Printer Pools & Load Balancing</h2>
        <p class="text-xs text-slate-400">Group physical printers for failover, round-robin, and high-availability dispatch</p>
    </div>
    <a href="{{ route('admin.pools.create') }}" class="btn-primary btn-sm">
        <x-icon name="plus" size="13" />
        <span>Create Pool</span>
    </a>
</div>

@if($pools->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($pools as $pool)
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-white">{{ $pool->name }}</h3>
                        <span class="badge {{ $pool->active ? 'badge-success' : 'badge-danger' }}">
                            {{ $pool->active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="badge badge-info uppercase">{{ str_replace('_', ' ', $pool->strategy) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('admin.pools.edit', $pool) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('admin.pools.destroy', $pool) }}" method="POST" onsubmit="return confirm('Delete this pool?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>

                @if($pool->description)
                    <p class="text-xs text-slate-400 mb-4">{{ $pool->description }}</p>
                @endif

                @php $poolPrinters = $pool->printers; @endphp
                <div class="mt-4 pt-3 border-t border-slate-800">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-2">
                        Printers in Pool ({{ $poolPrinters->count() }})
                    </span>
                    @if($poolPrinters->count() > 0)
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($poolPrinters->sortBy('priority') as $pp)
                                <div class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-xs text-slate-300 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $pp->active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                    <span class="font-mono">{{ $pp->printer_name }}</span>
                                    <span class="text-[10px] text-slate-500">(p: {{ $pp->priority }})</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-xs text-slate-500 italic">No printers assigned to this pool.</span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
@else
    <x-empty-state icon="🔄" title="No printer pools defined" description="Group multiple physical printers together to enable automatic failover and load balancing." actionText="Create Pool" :actionUrl="route('admin.pools.create')" />
@endif
@endsection
