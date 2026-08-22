@php
    $hero = is_array($hero ?? null) ? $hero : [];
    $title = is_scalar($hero['title'] ?? null) ? trim((string) $hero['title']) : '';
    $image = is_array($hero['image'] ?? null) && filled($hero['image']['url'] ?? null) ? $hero['image'] : null;
    $actions = array_values(array_filter([$hero['primary_action'] ?? null, $hero['secondary_action'] ?? null], 'is_array'));
    $metaItems = array_values(array_filter(is_array($hero['meta_items'] ?? null) ? $hero['meta_items'] : [], 'is_array'));
    $variant = in_array($hero['variant'] ?? null, ['default', 'split', 'modern-split', 'cover', 'minimal', 'centered', 'compact'], true) ? $hero['variant'] : 'default';
    $alignment = ($hero['alignment'] ?? null) === 'center' ? 'center' : 'start';
    $imagePosition = ($hero['image_position'] ?? null) === 'end' ? 'end' : 'start';
    $background = in_array($hero['background'] ?? null, ['muted', 'dark'], true) ? $hero['background'] : 'default';
@endphp

@if ($title !== '')
    <header @class(['content-block', 'shared-hero', $hero['class'] ?? null, "shared-hero--{$variant}", "shared-hero--align-{$alignment}", "content-block--align-{$alignment}", "content-block--{$background}" => $background !== 'default', "shared-hero--image-{$imagePosition}"]) dir="rtl">
        <div class="shared-hero__content">
            @if ($variant === 'modern-split' && (filled($hero['eyebrow'] ?? null) || filled($hero['icon'] ?? null)))
                <div class="shared-hero__eyebrow-line">
                    @if (filled($hero['icon'] ?? null)) <span class="shared-hero__icon" aria-hidden="true">@include('partials.blocks._icon', ['icon' => $hero['icon']])</span> @endif
                    @if (filled($hero['eyebrow'] ?? null)) <p class="shared-hero__eyebrow">{{ $hero['eyebrow'] }}</p> @endif
                </div>
            @else
                @if (filled($hero['eyebrow'] ?? null)) <p class="shared-hero__eyebrow">{{ $hero['eyebrow'] }}</p> @endif
                @if (filled($hero['icon'] ?? null)) <span class="shared-hero__icon" aria-hidden="true">@include('partials.blocks._icon', ['icon' => $hero['icon']])</span> @endif
            @endif
            @include('partials.blocks._heading', ['title' => $title, 'tag' => $hero['heading_tag'] ?? 'h1'])
            @include('partials.blocks._rich_text', ['content' => $hero['description'] ?? null, 'class' => 'shared-hero__description'])
            @if ($actions !== [])
                <div class="shared-hero__actions">
                    @foreach ($actions as $index => $action)
                        @include('partials.actions.render', ['label' => $action['label'] ?? null, 'presentation' => $action['presentation'] ?? null, 'class' => $action['class'] ?? ($index === 0 ? 'button shared-hero__primary' : 'button shared-hero__secondary')])
                    @endforeach
                </div>
            @endif
            @if ($metaItems !== [])
                <dl class="shared-hero__meta">
                    @foreach ($metaItems as $item)
                        <div>
                            @if (filled($item['icon'] ?? null)) <span aria-hidden="true">@include('partials.blocks._icon', ['icon' => $item['icon']])</span> @endif
                            @if (filled($item['label'] ?? null)) <dt>{{ $item['label'] }}</dt> @endif
                            @if (filled($item['value'] ?? null)) <dd>{{ $item['value'] }}</dd> @endif
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
        @if ($image)
            <figure class="shared-hero__media"><img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? $title }}" loading="{{ $image['loading'] ?? 'eager' }}" @if(filled($image['style'] ?? null)) style="{{ $image['style'] }}" @endif></figure>
        @endif
    </header>
@endif
