@extends('layouts.app')

@section('full_width_content', '1')

@section('content')
    <section class="form-page" dir="rtl">
        @if ($presentation['show_hero'])
            @include('partials.presentations.hero', ['hero' => $hero])
        @else
            <h1 class="form-page__title">{{ $presentation['title'] }}</h1>
        @endif
        <div class="form-page__body">
            @include('forms._form', ['form' => $form, 'fields' => $fields, 'instanceToken' => $instanceToken ?? null, 'pagePresentation' => $presentation])
        </div>
    </section>
@endsection
