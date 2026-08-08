@extends('client.layout')

@section('title', 'پروفایل | پرتال مشتریان')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">تنظیمات حساب</p><h1>پروفایل من</h1></div>
    </div>

    <div class="portal-stack">
        <x-client.card title="اطلاعات حساب کاربری">
            @if (session('status')) <p class="portal-success">اطلاعات پروفایل با موفقیت به‌روزرسانی شد.</p> @endif

            <form method="POST" action="{{ route('client.profile.update', $portalCustomer ? ['customer' => $portalCustomer->id] : []) }}" class="portal-stack portal-form">
                @csrf
                @method('PATCH')
                <div class="portal-info-grid">
                    <label class="portal-field">
                        <span>نام و نام خانوادگی</span>
                        <input name="name" value="{{ old('name', $portalUser->name) }}" autocomplete="name" required>
                        @error('name') <span class="portal-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="portal-field">
                        <span>ایمیل</span>
                        <input type="email" name="email" value="{{ old('email', $portalUser->email) }}" autocomplete="email">
                        @error('email') <span class="portal-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="portal-field">
                        <span>شماره موبایل</span>
                        <input value="{{ $portalUser->mobile }}" dir="ltr" disabled>
                    </label>
                    <div class="portal-field">
                        <span>حساب مشتری</span>
                        <input value="{{ $portalCustomer?->display_name ?? 'تخصیص داده نشده' }}" disabled>
                    </div>
                </div>
                <div><button class="portal-button" type="submit">ذخیره تغییرات</button></div>
            </form>
        </x-client.card>

        <x-client.card title="امنیت حساب">
            <p class="portal-muted">تغییر رمز عبور در حال حاضر از داخل پرتال فعال نیست. امکانات امنیتی بیشتر در نسخه‌های بعدی افزوده خواهد شد.</p>
        </x-client.card>
    </div>
@endsection
