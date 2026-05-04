{{-- Empty State Component --}}
@props(['icon' => '📋', 'title' => 'No items found', 'description' => null, 'actionText' => null, 'actionUrl' => null])

<div style="text-align: center; padding: 3rem 2rem;">
    <div style="font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.5;">{{ $icon }}</div>
    <h3 style="font-size: 1rem; font-weight: 600; color: var(--text); margin-bottom: 0.35rem;">{{ $title }}</h3>
    @if($description)
    <p style="color: var(--text-muted); font-size: 0.85rem; max-width: 360px; margin: 0 auto 1.25rem;">{{ $description }}</p>
    @endif
    @if($actionText && $actionUrl)
    <a href="{{ $actionUrl }}" class="btn btn-primary" style="text-decoration: none;">{{ $actionText }}</a>
    @endif
</div>
