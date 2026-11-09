@extends('client.layout')

@section('title', 'ورود به حساب کاربری')

@section('content')
    <section class="portal-auth-card">
        <p class="portal-eyebrow">حساب کاربری</p>
        <h1>ورود به حساب</h1>
        <p class="portal-muted">شماره موبایل و رمز عبور خود را وارد کنید.</p>

        <form method="POST" action="{{ route('client.login.store') }}" class="portal-stack">
            @csrf
            @if (session('url.intended') === route('checkout.create'))<input type="hidden" name="continue" value="checkout">@endif
            @include('client.auth.partials.fields', ['mode' => 'login', 'prefix' => 'page-login'])
            <button class="portal-button" type="submit">ورود به حساب</button>
        </form>

        <p class="portal-auth-switch">حساب ندارید؟ <a href="{{ route('register', session('url.intended') === route('checkout.create') ? ['continue' => 'checkout'] : []) }}">ثبت‌نام</a></p>
    </section>
@endsection
