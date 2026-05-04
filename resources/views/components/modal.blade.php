{{-- Unified Modal Component --}}
@props(['id' => 'modal', 'title' => '', 'maxWidth' => 'lg'])

@php
$widths = [
    'sm' => '360px',
    'md' => '480px',
    'lg' => '640px',
    'xl' => '800px',
];
$w = $widths[$maxWidth] ?? $widths['lg'];
@endphp

<dialog id="{{ $id }}" class="ph-modal" style="
    padding: 0; border: 1px solid var(--border); border-radius: 12px;
    background: var(--surface); color: var(--text); width: {{ $w }};
    max-width: 95vw; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
" {{ $attributes }}>
    <div style="padding: 1.5rem;">
        @if($title)
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.1rem; font-weight: 600;">{{ $title }}</h2>
            <button type="button" onclick="document.getElementById('{{ $id }}').close()"
                style="background: none; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; padding: 4px; line-height: 1;">&times;</button>
        </div>
        @endif
        {{ $slot }}
    </div>
</dialog>

<style>
.ph-modal::backdrop {
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(2px);
}
.ph-modal[open] {
    animation: modalIn 0.2s ease;
}
@keyframes modalIn {
    from { opacity: 0; transform: translateY(-10px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
