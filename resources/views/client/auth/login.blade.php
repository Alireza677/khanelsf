@extends('client.layout')

@section('title', 'ورود به پرتال مشتریان')

@section('content')
    <section class="portal-auth-card">
        <p class="portal-eyebrow">پرتال مشتریان</p>
        <h1>ورود به حساب</h1>
        <p class="portal-muted">شماره موبایل و رمز عبور موقت خود را وارد کنید.</p>

        <form method="POST" action="{{ route('client.login.store') }}" class="portal-stack">
            @csrf
            <label class="portal-field">
                <span>شماره موبایل</span>
                <input name="mobile" value="{{ old('mobile') }}" inputmode="tel" autocomplete="username" dir="ltr" required autofocus>
                @error('mobile') <span class="portal-error">{{ $message }}</span> @enderror
            </label>
            <label class="portal-field">
                <span>رمز عبور</span>
                <input type="password" name="password" autocomplete="current-password" dir="ltr" required>
                @error('password') <span class="portal-error">{{ $message }}</span> @enderror
            </label>
            <button class="portal-button" type="submit">ورود به پرتال</button>
        </form>

        <p class="portal-auth-switch">حساب ندارید؟ <a href="{{ route('register') }}">ثبت‌نام</a></p>
    </section>
@endsection
