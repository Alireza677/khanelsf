@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
    $images = collect($data['images'] ?? [])->filter(fn ($image) => ! empty($image['url']));
@endphp

@include('partials.blocks._image_control_styles')

<section @class([
    'content-block',
    "content-block--{$background}" => $background !== 'default',
    "content-block--align-{$alignment}",
])>
    <div class="block-heading">
        @if (! empty($data['eyebrow']))
            <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
        @endif

        @if (! empty($data['section_title']))
            <h2>{{ $data['section_title'] }}</h2>
        @endif
    </div>

    @if ($images->isNotEmpty())
        <div class="block-gallery">
            @foreach ($images as $image)
                <img
                    class="block-configured-image"
                    src="{{ $image['url'] }}"
                    alt="{{ $image['alt'] ?? '' }}"
                    style="{{ \App\Support\BlockImageStyle::imageVariables($image, 'image') }}"
                >
            @endforeach
        </div>
    @else
        <p class="empty-state">No gallery images have been added yet.</p>
    @endif
</section>
