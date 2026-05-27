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
" role="dialog" aria-modal="true" @if($title) aria-labelledby="{{ $id }}-title" @endif aria-describedby="{{ $id }}-body" {{ $attributes }}>
    <div style="padding: 1.5rem;">
        @if($title)
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 id="{{ $id }}-title" style="font-size: 1.1rem; font-weight: 600;">{{ $title }}</h2>
            <button type="button" onclick="document.getElementById('{{ $id }}').close()"
                style="background: none; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; padding: 4px; line-height: 1;" aria-label="Close {{ $title }}">&times;</button>
        </div>
        @endif
        <div id="{{ $id }}-body">
        {{ $slot }}
        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('{{ $id }}');
    if (!modal) return;

    // Focus trapping
    modal.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modal.close();
            return;
        }

        if (e.key === 'Tab') {
            const focusable = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }
    });

    // Set aria-hidden on background content when modal opens/closes
    modal.addEventListener('open', function() {
        document.body.querySelectorAll('.layout, .toast-container, .hamburger').forEach(function(el) {
            if (el) el.setAttribute('aria-hidden', 'true');
        });
    });

    modal.addEventListener('close', function() {
        document.body.querySelectorAll('.layout, .toast-container, .hamburger').forEach(function(el) {
            if (el) el.removeAttribute('aria-hidden');
        });
    });
});
</script>
