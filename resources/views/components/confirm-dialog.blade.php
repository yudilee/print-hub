{{-- Styled Confirmation Dialog --}}
@props(['id' => 'confirm-dialog', 'title' => 'Confirm Action', 'message' => 'Are you sure?', 'confirmText' => 'Confirm', 'cancelText' => 'Cancel', 'danger' => false, 'formAction' => '', 'formMethod' => 'POST'])

<x-modal id="{{ $id }}" maxWidth="sm" {{ $attributes }}>
    <div style="text-align: center;">
        @if($danger)
        <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(239,68,68,0.1); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 1.5rem;">⚠️</div>
        @endif
        <p style="color: var(--text); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">{{ $message }}</p>
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
            <button type="button" onclick="document.getElementById('{{ $id }}').close()"
                class="btn btn-secondary">{{ $cancelText }}</button>
            <form method="{{ $formMethod }}" action="{{ $formAction }}" style="display: inline;">
                @csrf
                @if(in_array($formMethod, ['PUT', 'DELETE', 'PATCH']))
                    @method($formMethod)
                @endif
                <button type="submit" class="btn {{ $danger ? 'btn-danger' : 'btn-primary' }}">{{ $confirmText }}</button>
            </form>
        </div>
    </div>
</x-modal>
