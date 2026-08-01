@extends('layouts.app')

@section('content')
    <section class="contact-page">
        <h1>{{ $form->name }}</h1>

        @include('forms._form', ['form' => $form, 'fields' => $fields])
    </section>
@endsection
