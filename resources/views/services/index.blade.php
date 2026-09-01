@extends('layouts.app')

@section('content')
    <div class="services-archive">
        @include('services.partials.archive-groups', [
            'serviceArchive' => $serviceArchive,
            'actionLabel' => 'مشاهده جزئیات',
        ])
    </div>
@endsection
