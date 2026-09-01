{{-- Empty State Component --}}
@props(['icon' => '📋', 'title' => 'No items found', 'description' => null, 'actionText' => null, 'actionUrl' => null])

<div class="text-center py-12 px-6">
    <div class="w-16 h-16 rounded-2xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm text-slate-300">
        {{ $icon }}
    </div>
    <h3 class="text-base font-bold text-white mb-1">{{ $title }}</h3>
    @if($description)
    <p class="text-xs text-slate-400 max-w-sm mx-auto mb-6">{{ $description }}</p>
    @endif
    @if($actionText && $actionUrl)
    <a href="{{ $actionUrl }}" class="btn-primary">
        <x-icon name="plus" size="14" />
        <span>{{ $actionText }}</span>
    </a>
    @endif
</div>
