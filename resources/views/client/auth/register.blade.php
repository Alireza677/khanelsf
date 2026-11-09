@extends('client.layout')

@section('title', 'ایجاد حساب کاربری')

@section('content')
    <section class="portal-auth-card">
        <p class="portal-eyebrow">حساب کاربری</p>
        <h1>ایجاد حساب کاربری</h1>

        <form method="POST" action="{{ route('client.register.store') }}" class="portal-stack">
            @csrf
            @if (session('url.intended') === route('checkout.create'))<input type="hidden" name="continue" value="checkout">@endif
            @include('client.auth.partials.fields', ['mode' => 'register', 'prefix' => 'page-register'])
            <button class="portal-button" type="submit">ثبت‌نام</button>
        </form>

        <p class="portal-auth-switch">قبلاً حساب دارید؟ <a href="{{ route('login', session('url.intended') === route('checkout.create') ? ['continue' => 'checkout'] : []) }}">ورود</a></p>
    </section>
@endsection
