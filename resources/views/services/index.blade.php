@extends('layouts.app')

@section('content')
    <div class="services-archive">
        @include('partials.presentations.collection', [
            'collection' => $collection,
            'collectionEyebrow' => 'خدمات',
            'collectionActionLabel' => 'مشاهده جزئیات',
        ])
    </div>
@endsection
