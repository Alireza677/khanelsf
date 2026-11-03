<ul>
    @foreach ($items as $item)
        <li>
            <a href="{{ $item['url'] }}" @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif>
                <bdi>{{ $item['label'] }}</bdi>
                <span class="corporate-footer__menu-arrow" aria-hidden="true">@include('partials.blocks._icon', ['icon' => 'heroicon-o-chevron-left'])</span>
            </a>
            @if ($item['children'] !== [])
                @include('partials.blocks.site-footer.navigation', ['items' => $item['children']])
            @endif
        </li>
    @endforeach
</ul>
