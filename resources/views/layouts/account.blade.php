@extends('layouts.app')

@section('content')
    <section class="public-account-shell" data-public-account-layout>
        @include('client.partials.account-navigation', ['account' => $accountNavigation])

        <div class="public-account-shell__content">
            @yield('account-content')
        </div>
    </section>
@endsection
