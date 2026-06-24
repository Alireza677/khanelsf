@php
    $overlayOpacity = (int) ($data['overlay_opacity'] ?? 45);
    $overlayOpacity = max(0, min(90, $overlayOpacity));
    $overlayStart = number_format($overlayOpacity / 100, 2, '.', '');
    $overlayEnd = number_format(min(85, $overlayOpacity + 18) / 100, 2, '.', '');
    $backgroundImage = filled($data['image'] ?? null)
        ? "background-image: linear-gradient(rgba(15, 23, 42, {$overlayStart}), rgba(15, 23, 42, {$overlayEnd})), url('".e($data['image'])."');"
        : null;
    $backgroundVariables = \App\Support\BlockImageStyle::backgroundVariables($data, 'image');
    $description = $data['subtitle'] ?? $data['description'] ?? null;
@endphp

@include('partials.blocks._image_control_styles')

<section
    class="content-block hero-template-1 block-configured-background"
    @if ($backgroundImage || $backgroundVariables) style="{!! trim($backgroundImage.' '.$backgroundVariables, ' ;') !!}" @endif
>
    <div class="hero-template-1__inner">
        @if (! empty($data['eyebrow']))
            <p class="hero-template-1__eyebrow">{{ $data['eyebrow'] }}</p>
        @endif

        @if (! empty($data['title']))
            <h1>{{ $data['title'] }}</h1>
        @endif

        @if (! empty($description))
            <p class="hero-template-1__description">{{ $description }}</p>
        @endif

        @if ((! empty($data['primary_button_label']) && ! empty($data['primary_button_url'])) || (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url'])))
            <div class="hero-template-1__actions">
                @if (! empty($data['primary_button_label']) && ! empty($data['primary_button_url']))
                    <a class="button" href="{{ $data['primary_button_url'] }}">{{ $data['primary_button_label'] }}</a>
                @endif

                @if (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url']))
                    <a class="button hero-template-1__secondary" href="{{ $data['secondary_button_url'] }}">{{ $data['secondary_button_label'] }}</a>
                @endif
            </div>
        @endif
    </div>
</section>
