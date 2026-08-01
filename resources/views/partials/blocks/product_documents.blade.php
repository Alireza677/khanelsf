@php
    $data = app(\App\CMS\Blocks\Product\ProductDocumentsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $documents = collect(data_get($context, 'media.documents', []))
        ->filter(fn ($document) => filled(data_get($document, 'url')));
@endphp

@if ($documents->isNotEmpty())
    <section class="content-block product-documents" dir="rtl">
        @include('partials.blocks._heading', ['title' => $data['content']['title'] ?: 'اسناد و فایل‌ها', 'tag' => data_get($data, 'settings.heading_tag', 'h2')])

        <ul class="product-documents__list">
            @foreach ($documents as $document)
                <li>
                    <a href="{{ data_get($document, 'url') }}" target="_blank" rel="noopener noreferrer">
                        {{ data_get($document, 'title') ?: data_get($document, 'originalName') ?: 'دریافت فایل' }}
                    </a>
                    @if ($data['settings']['show_type'] && filled(data_get($document, 'mimeType')))
                        <small>{{ data_get($document, 'mimeType') }}</small>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
