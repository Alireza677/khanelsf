@extends('layouts.app')

@section('content')
    @if ($page?->hasBlocks())
        @include('partials.page-blocks', ['blocks' => $page->blocks])
    @else
        <section class="home-hero">
            <div class="home-hero__content">
                <h1>{{ $page?->title ?? config('app.name') }}</h1>

                {!! $page?->content ?? '<p>به سایت خوش آمدید. محتوای صفحه اصلی را از پنل مدیریت ویرایش کنید.</p>' !!}

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
