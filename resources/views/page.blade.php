@extends('layouts.app')

@section('content')
    @if ($page?->hasBlocks())
        @include('partials.page-blocks', [
            'blocks' => $page->blocks,
            'context' => [
                'page_id' => $page->getKey(),
                'page_url' => request()->getRequestUri(),
                'preview' => ! empty($isPreview),
            ],
        ])
    @else
        <article>
            <h1>{{ $page?->title ?? config('app.name') }}</h1>

            @if ($page?->featuredImageUrl())
                <img src="{{ $page->featuredImageUrl() }}" alt="{{ $page->title }}">
            @endif

            {{ \App\Support\RichText::render($page?->content ?? '<p>Welcome to the starter CMS.</p>') }}
        </article>
    @endif
@endsection
