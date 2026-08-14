@extends('client.layout')

@section('title', 'ایجاد حساب کاربری')

@section('content')
    <section class="portal-auth-card">
        <p class="portal-eyebrow">حساب کاربری</p>
        <h1>ایجاد حساب کاربری</h1>

        <form method="POST" action="{{ route('client.register.store') }}" class="portal-stack">
            @csrf
            <label class="portal-field">
                <span>نام و نام خانوادگی</span>
                <input name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name') <span class="portal-error">{{ $message }}</span> @enderror
            </label>
            <label class="portal-field">
                <span>شماره موبایل</span>
                <input name="mobile" value="{{ old('mobile') }}" inputmode="tel" autocomplete="tel" dir="ltr" required>
                @error('mobile') <span class="portal-error">{{ $message }}</span> @enderror
            </label>
            <label class="portal-field">
                <span>ایمیل (اختیاری)</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" dir="ltr">
                @error('email') <span class="portal-error">{{ $message }}</span> @enderror
            </label>
            <label class="portal-field">
                <span>رمز عبور</span>
                <input type="password" name="password" autocomplete="new-password" dir="ltr" required>
                @error('password') <span class="portal-error">{{ $message }}</span> @enderror
            </label>
            <label class="portal-field">
                <span>تکرار رمز عبور</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" dir="ltr" required>
            </label>
            <button class="portal-button" type="submit">ثبت‌نام</button>
        </form>

        <p class="portal-auth-switch">قبلاً حساب دارید؟ <a href="{{ route('login') }}">ورود</a></p>
    </section>
@endsection
