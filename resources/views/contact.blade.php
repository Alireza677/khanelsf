@extends('layouts.app')

@section('content')
    <section class="contact-page">
        <h1>{{ $page?->title ?? 'تماس با ما' }}</h1>

        @if ($page?->content)
            {{ \App\Support\RichText::render($page->content) }}
        @endif

        @if (session('success'))
            <p class="form-status" role="status">{{ session('success') }}</p>
        @endif

        @if ($errors->any())
            <div class="form-error" role="alert">
                <p>لطفا فرم را بررسی کنید و دوباره تلاش کنید.</p>
            </div>
        @endif

        <form class="form-card" method="post" action="{{ route('contact.store') }}">
            @csrf

            <div class="form-honeypot" aria-hidden="true">
                <label for="website">وب‌سایت</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="name">نام</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                @error('name')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email">ایمیل</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone">تلفن</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone') }}">
                @error('phone')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="subject">موضوع</label>
                <input id="subject" name="subject" type="text" value="{{ old('subject') }}">
                @error('subject')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="message">پیام</label>
                <textarea id="message" name="message" rows="6" required>{{ old('message') }}</textarea>
                @error('message')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <button class="button" type="submit">ارسال</button>
        </form>
    </section>
@endsection
