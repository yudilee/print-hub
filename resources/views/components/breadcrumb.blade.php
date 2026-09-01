{{-- Breadcrumb Navigation --}}
@props(['items' => []])

@if(!empty($items))
<nav class="mb-4 flex items-center gap-2 text-xs text-slate-400" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-400 transition flex items-center gap-1">
        <x-icon name="dashboard" size="13" />
        <span>Home</span>
    </a>
    @foreach($items as $i => $item)
        <x-icon name="chevron-right" size="12" class="text-slate-600 dark:text-slate-600" />
        @if(!$loop->last && isset($item['url']))
            <a href="{{ $item['url'] }}" class="hover:text-blue-400 transition text-slate-300 font-medium">{{ $item['label'] }}</a>
        @else
            <span class="text-white font-semibold">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
@endif
