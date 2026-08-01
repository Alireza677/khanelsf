@php
    $formId = data_get($data, 'content.form_id');
    $form = is_numeric($formId) && (int) $formId > 0
        ? \App\Models\Form::query()->published()->find((int) $formId)
        : null;
    $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
    $style = in_array($settings['style'] ?? null, ['default', 'card'], true)
        ? $settings['style']
        : 'default';
    $container = in_array($settings['container'] ?? null, ['default', 'narrow', 'full'], true)
        ? $settings['container']
        : 'default';
    $displayTitle = is_string($settings['title'] ?? null) && trim($settings['title']) !== ''
        ? trim($settings['title'])
        : $form?->name;
    $description = is_string($settings['description'] ?? null) && trim($settings['description']) !== ''
        ? trim($settings['description'])
        : null;
    $formContext = [
        'page_id' => $context['page_id'] ?? null,
        'page_url' => $context['page_url'] ?? request()->getRequestUri(),
        'block_id' => is_string($data['block_id'] ?? null) ? $data['block_id'] : null,
    ];
@endphp

@if ($form)
    <section @class([
        'content-block',
        'block-form',
        "block-form--{$style}",
        "block-form--container-{$container}",
    ])>
        @include('forms.embed', [
            'form' => $form,
            'displayTitle' => $displayTitle,
            'headingTag' => $settings['heading_tag'] ?? 'h2',
            'description' => $description,
            'attributionContext' => $formContext,
        ])
    </section>
@endif
