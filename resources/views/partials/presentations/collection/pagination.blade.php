@if ($collection->pagination)
    <nav class="shared-collection__pagination" aria-label="{{ $collection->pagination->ariaLabel }}">
        @foreach ($collection->pagination->links as $link)
            @if ($link->url)
                <a href="{{ $link->url }}" @if($link->active) aria-current="page" @endif>{{ $link->label }}</a>
            @else
                <span @if($link->active) aria-current="page" @endif>{{ $link->label }}</span>
            @endif
        @endforeach
    </nav>
@endif
