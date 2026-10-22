@extends('layouts.app')

@section('content')
    @include('videos.partials.archive', ['archive' => $archive])
@endsection
