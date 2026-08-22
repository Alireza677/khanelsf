@php
    $template = $formBlock['template'];
    $content = $formBlock['content'];
    $settings = $formBlock['settings'];
    $form = $formBlock['form'];
    $style = $settings['style'];
    $container = $settings['container'];
    $displayTitle = filled($settings['title']) ? $settings['title'] : $form?->name;
@endphp

@if ($formBlock['available'])
    <section @class([
        'content-block',
        'block-form',
        "block-form--{$style}",
        "block-form--container-{$container}",
        'block-form--split' => $template === 'split',
    ])>
        @if ($template === 'split')
            <div class="block-form__split-content">
                @if (filled($content['eyebrow']))
                    <p class="block-eyebrow">{{ $content['eyebrow'] }}</p>
                @endif

                @if (filled($displayTitle))
                    @include('partials.blocks._heading', ['title' => $displayTitle, 'tag' => $settings['heading_tag']])
                @endif

                @include('partials.blocks._rich_text', [
                    'content' => $settings['description'],
                    'class' => 'block-form__description',
                ])

                @if (filled($content['media']['url']))
                    <img class="block-form__image" src="{{ $content['media']['url'] }}" alt="{{ $content['media']['alt'] ?? '' }}" loading="lazy">
                @endif
            </div>

            <div class="block-form__split-form">
                @include('forms.embed', [
                    'form' => $form,
                    'fields' => $formBlock['fields'],
                    'displayTitle' => '',
                    'description' => null,
                    'attributionContext' => $formBlock['attribution'],
                    'instanceToken' => $formBlock['instance_token'],
                ])
            </div>
        @else
            @include('forms.embed', [
                'form' => $form,
                'fields' => $formBlock['fields'],
                'displayTitle' => $displayTitle,
                'headingTag' => $settings['heading_tag'],
                'description' => $settings['description'],
                'attributionContext' => $formBlock['attribution'],
                'instanceToken' => $formBlock['instance_token'],
            ])
        @endif
    </section>
@elseif ($formBlock['preview'])
    <section class="content-block block-form block-form--unavailable">فرم منتشرشده‌ای برای این بلوک در دسترس نیست.</section>
@endif
