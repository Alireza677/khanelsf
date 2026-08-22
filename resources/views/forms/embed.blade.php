<div class="embedded-form">
    @if (filled($displayTitle ?? $form->name))
        @php($headingTag = \App\CMS\Blocks\Support\HeadingLevel::normalize($headingTag ?? null))
        <{{ $headingTag }}>{{ $displayTitle ?? $form->name }}</{{ $headingTag }}>
    @endif

    @if (filled($description ?? null))
        @include('partials.blocks._rich_text', [
            'content' => $description,
            'class' => 'embedded-form__description',
        ])
    @endif

    @include('forms._form', [
        'form' => $form,
        'fields' => $fields ?? null,
        'attributionContext' => $attributionContext ?? [],
        'instanceToken' => $instanceToken ?? null,
    ])
</div>
