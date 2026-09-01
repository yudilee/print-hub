{{-- Unified Modal Component --}}
@props(['id' => 'modal', 'title' => '', 'maxWidth' => 'lg'])

@php
$maxWidthClasses = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-2xl',
    '2xl' => 'max-w-4xl',
];
$wClass = $maxWidthClasses[$maxWidth] ?? 'max-w-lg';
@endphp

<dialog id="{{ $id }}" class="ph-modal fixed inset-0 m-auto p-0 border border-slate-800 bg-slate-900 text-slate-100 rounded-2xl shadow-2xl backdrop:bg-slate-950/80 backdrop:backdrop-blur-xs w-full {{ $wClass }} max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" @if($title) aria-labelledby="{{ $id }}-title" @endif aria-describedby="{{ $id }}-body" {{ $attributes }}>
    <div class="p-6">
        @if($title)
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h2 id="{{ $id }}-title" class="text-base font-bold text-white">{{ $title }}</h2>
            <button type="button" onclick="document.getElementById('{{ $id }}').close()"
                class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" aria-label="Close {{ $title }}">
                <x-icon name="x" size="18" />
            </button>
        </div>
        @endif
        <div id="{{ $id }}-body">
            {{ $slot }}
        </div>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('{{ $id }}');
    if (!modal) return;

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.close();
        }
    });

    modal.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modal.close();
            return;
        }

        if (e.key === 'Tab') {
            const focusable = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (focusable.length === 0) return;
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
});
</script>
