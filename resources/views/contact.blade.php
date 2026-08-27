@extends('layouts.app')

@section('content')
    <section class="contact-page">
        <h1>{{ $page?->title ?? 'تماس با ما' }}</h1>

        @if ($page?->content)
            {{ \App\Support\RichText::render($page->content) }}
        @endif
    </section>
@endsection
