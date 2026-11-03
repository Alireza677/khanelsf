<a class="corporate-footer__link {{ $linkClass ?? '' }}" href="{{ $link['presentation']['href'] }}"
    @if ($link['presentation']['target']) target="{{ $link['presentation']['target'] }}" @endif
    @if ($link['presentation']['rel']) rel="{{ $link['presentation']['rel'] }}" @endif>
    @if (! empty($link['image']))
        @include('partials.blocks.site-footer.image', ['image' => $link['image']])
    @endif
    @if (! empty($link['icon']))
        <span aria-hidden="true">@include('partials.blocks._icon', ['icon' => $link['icon']])</span>
    @endif
    @if (filled($link['label'])) <bdi dir="{{ $direction ?? 'auto' }}" @class(['corporate-footer__visually-hidden' => $iconOnly ?? false])>{{ $link['label'] }}</bdi> @endif
</a>
