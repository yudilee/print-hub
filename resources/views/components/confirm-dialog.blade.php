{{-- Styled Confirmation Dialog --}}
@props(['id' => 'confirm-dialog', 'title' => 'Confirm Action', 'message' => 'Are you sure?', 'confirmText' => 'Confirm', 'cancelText' => 'Cancel', 'danger' => false, 'formAction' => '', 'formMethod' => 'POST'])

<x-modal id="{{ $id }}" maxWidth="sm" {{ $attributes }}>
    <div class="text-center py-2">
        @if($danger)
        <div class="w-12 h-12 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-500 flex items-center justify-center mx-auto mb-4">
            <x-icon name="warning" size="24" />
        </div>
        @else
        <div class="w-12 h-12 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center mx-auto mb-4">
            <x-icon name="info" size="24" />
        </div>
        @endif

        <h3 class="text-base font-bold text-white mb-2">{{ $title }}</h3>
        <p class="text-xs text-slate-400 leading-relaxed mb-6">{{ $message }}</p>

        <div class="flex items-center justify-center gap-3">
            <button type="button" onclick="document.getElementById('{{ $id }}').close()"
                class="btn-secondary">{{ $cancelText }}</button>
            <form method="{{ in_array(strtoupper($formMethod), ['GET', 'POST']) ? $formMethod : 'POST' }}" action="{{ $formAction }}" class="inline">
                @csrf
                @if(!in_array(strtoupper($formMethod), ['GET', 'POST']))
                    @method($formMethod)
                @endif
                <button type="submit" class="{{ $danger ? 'btn-danger' : 'btn-primary' }}">{{ $confirmText }}</button>
            </form>
        </div>
    </div>
</x-modal>
