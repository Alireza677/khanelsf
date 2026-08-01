@extends('layouts.app')

@section('content')
    @if ($template->type === 'site_header')
        <section class="template-header-preview-note" aria-label="پیش‌نمایش قالب هدر">
            <p>پیش‌نمایش هدر در بالای همین صفحه نمایش داده شده است.</p>
        </section>
    @else
        @include('partials.page-blocks', [
            'blocks' => $template->blocks,
            'context' => $templateContext ?? [],
        ])
    @endif
@endsection
