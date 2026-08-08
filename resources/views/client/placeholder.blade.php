@extends('client.layout')

@section('title', $title.' | پرتال مشتریان')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">پرتال مشتریان</p><h1>{{ $title }}</h1></div>
    </div>
    <x-client.empty-state :title="$title" :message="$message" :icon="$icon" />
@endsection
