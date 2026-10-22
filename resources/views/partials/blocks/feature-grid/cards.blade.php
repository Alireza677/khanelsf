@php($settings = $grid['settings'])
<div @class(['block-grid', 'block-grid--dynamic' => $grid['dynamic']]) @if ($grid['grid_style']) style="{{ $grid['grid_style'] }} --feature-grid-item-count: {{ count($grid['items']) }};" @endif>
    @foreach ($grid['items'] as $item)
        <article class="block-card">
            @php($hasMedia = ! empty($item['image']) || ! empty($item['icon']))
            @if ($settings['variant'] === 'icon_list' && $hasMedia)<div class="block-card__media">@endif
                @if (! empty($item['video']))
                    <button type="button" class="feature-video" data-feature-video-play
                        data-video-embed="{{ $item['video']['embed_url'] }}"
                        data-video-title="{{ $item['video']['title'] }}"
                        aria-label="پخش ویدئو: {{ $item['title'] ?? $item['video']['title'] }}" aria-haspopup="dialog">
                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] ?? '' }}" loading="lazy" referrerpolicy="no-referrer">
                        <span class="feature-video__play" aria-hidden="true">▶</span>
                        @if ($item['video']['duration'] !== null)
                            <span class="feature-video__duration" dir="ltr">{{ $item['video']['duration'] }}</span>
                        @endif
                    </button>
                @elseif (! empty($item['image']))
                    <img @class(['block-configured-image' => ! $grid['dynamic']])
                        src="{{ $item['image'] }}" alt="{{ $item['title'] ?? '' }}"
                        @if (! $grid['dynamic']) style="{{ \App\Support\BlockImageStyle::imageVariables($item, 'image') }}" @endif>
                @elseif (! empty($item['icon']))
                    <div class="block-card__icon">
                        @include('partials.blocks._icon', ['icon' => $item['icon'], 'size' => $item['icon_size'] ?? null])
                    </div>
                @endif
            @if ($settings['variant'] === 'icon_list' && $hasMedia)</div>@endif

            @if ($settings['variant'] === 'icon_list')<div class="block-card__content">@endif
            @if (! empty($item['title']))
                <h3>{{ $item['title'] }}</h3>
            @endif
            @if (! empty($item['video_unavailable']))
                <p role="status">{{ $item['video_unavailable'] }}</p>
            @endif
            @include('partials.blocks._rich_text', ['content' => $item['description'] ?? null, 'class' => 'block-card__description'])
            @include('partials.actions.render', [
                'label' => $item['button_label'] ?? null,
                'class' => 'button block-card__button',
                'presentation' => $item['presentation'] ?? null,
            ])
            @if ($settings['variant'] === 'icon_list')</div>@endif
        </article>
    @endforeach
</div>
