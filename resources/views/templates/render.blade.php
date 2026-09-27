@extends('layouts.app')

@section('content')
    @if (in_array($template->type, ['site_header', 'site_footer'], true))
        <section class="template-header-preview-note" aria-label="پیش‌نمایش قالب سراسری">
            <p>
                پیش‌نمایش {{ $template->type === 'site_header' ? 'هدر در بالای' : 'فوتر در پایین' }} همین صفحه نمایش داده شده است.
            </p>
        </section>
    @else
        @isset($projectGalleryFilters)
            @include('projects.partials.filters')
        @endisset
        @if ($template->type === 'blog_index')
            <div class="blog-archive archive-collection-page">
        @endif
        @if ($template->type === 'projects_index')
            <div @class(['project-gallery-archive', 'project-gallery-archive--filterable' => isset($projectGalleryFilters)])>
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
