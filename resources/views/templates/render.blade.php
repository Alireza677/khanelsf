@extends('layouts.app')

@section('content')
    @include('partials.page-blocks', [
        'blocks' => $template->blocks,
        'context' => $templateContext ?? [],
    ])
@endsection
