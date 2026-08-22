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
        <section class="home-hero">
            <div class="home-hero__content">
                <h1>{{ $page?->title ?? config('app.name') }}</h1>

                {{ \App\Support\RichText::render($page?->content ?? '<p>به سایت خوش آمدید. محتوای صفحه اصلی را با بلوک‌ها از پنل مدیریت بسازید.</p>') }}

                <p>
                    <a class="button" href="{{ route('contact.create') }}">تماس با ما</a>
                </p>
            </div>

            @if ($page?->featuredImageUrl())
                <img class="home-hero__image" src="{{ $page->featuredImageUrl() }}" alt="{{ $page->title }}">
            @endif
        </section>
    @endif
@endsection
