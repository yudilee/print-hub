{{-- Breadcrumb Navigation --}}
@props(['items' => []])

@if(!empty($items))
<nav style="margin-bottom: 1.25rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem; color: var(--text-muted);" aria-label="Breadcrumb">
    @foreach($items as $i => $item)
        @if($i > 0)
            <span style="opacity: 0.4;">/</span>
        @endif
        @if(!$loop->last && isset($item['url']))
            <a href="{{ $item['url'] }}" style="color: var(--primary); text-decoration: none;">{{ $item['label'] }}</a>
        @else
            <span style="color: {{ $loop->last ? 'var(--text)' : 'var(--text-muted)' }}; font-weight: {{ $loop->last ? '600' : '400' }};">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
@endif
