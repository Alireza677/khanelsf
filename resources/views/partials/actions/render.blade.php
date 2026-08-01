@if (filled($label) && is_array($presentation))
    @if (($presentation['kind'] ?? null) === 'link')
        <a
            class="{{ $class }}"
            href="{{ $presentation['href'] }}"
            @if (filled($presentation['target'] ?? null)) target="{{ $presentation['target'] }}" @endif
            @if (filled($presentation['rel'] ?? null)) rel="{{ $presentation['rel'] }}" @endif
            @if (($presentation['prevent_default'] ?? false) === true) data-action-placeholder @endif
        >{{ $label }}</a>
    @elseif (($presentation['kind'] ?? null) === 'form')
        <form
            method="post"
            action="{{ $presentation['action'] }}"
            @if (filled($presentation['modal_url'] ?? null))
                data-form-action-modal-url="{{ $presentation['modal_url'] }}"
                data-form-action-fallback-url="{{ $presentation['page_url'] }}"
            @endif
        >
            @csrf
            <input type="hidden" name="_context_page_id" value="{{ data_get($presentation, 'context.page_id') }}">
            <input type="hidden" name="_context_page_url" value="{{ data_get($presentation, 'context.page_url') }}">
            <input type="hidden" name="_context_block_id" value="{{ data_get($presentation, 'context.block_id') }}">
            <button
                class="{{ $class }}"
                type="submit"
                @if (filled($presentation['modal_url'] ?? null)) aria-haspopup="dialog" @endif
            >{{ $label }}</button>
        </form>
    @endif
@endif
