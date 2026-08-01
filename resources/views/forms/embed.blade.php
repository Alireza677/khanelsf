<div class="embedded-form">
    @if (filled($displayTitle ?? $form->name))
        @php($headingTag = \App\CMS\Blocks\Support\HeadingLevel::normalize($headingTag ?? null))
        <{{ $headingTag }}>{{ $displayTitle ?? $form->name }}</{{ $headingTag }}>
    @endif

    @if (filled($description ?? null))
        <p class="embedded-form__description">{{ $description }}</p>
    @endif

    @include('forms._form', [
        'form' => $form,
        'attributionContext' => $attributionContext ?? [],
    ])
</div>
