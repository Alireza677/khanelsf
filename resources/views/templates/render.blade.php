@extends('layouts.app')

@section('content')
    @if ($template->type === 'site_header')
        <section class="template-header-preview-note" aria-label="پیش‌نمایش قالب هدر">
            <p>پیش‌نمایش هدر در بالای همین صفحه نمایش داده شده است.</p>
        </section>
    @else
        @if ($template->type === 'blog_index')
            <div class="blog-archive archive-collection-page">
        @endif
        @if ($template->type === 'projects_index')
            <div class="project-gallery-archive">
        @endif
        <div @class([
            'service-detail' => $template->type === 'service_single',
            'project-case-study' => $template->type === 'project_single',
            'services-archive' => in_array($template->type, ['service_index', 'blog_index'], true),
        ])>
            @include('partials.page-blocks', [
                'blocks' => $template->blocks,
                'context' => $templateContext ?? [],
            ])
        </div>
        @if ($template->type === 'blog_index')
            </div>
        @endif
        @if ($template->type === 'projects_index')
            </div>
        @endif
    @endif
@endsection
