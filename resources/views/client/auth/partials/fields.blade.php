@php
    $registration = $mode === 'register';
    $modal = $modal ?? false;
    $fields = $registration
        ? ['name' => 'نام و نام خانوادگی', 'mobile' => 'شماره موبایل', 'email' => 'ایمیل (اختیاری)', 'password' => 'رمز عبور', 'password_confirmation' => 'تکرار رمز عبور']
        : ['mobile' => 'شماره موبایل', 'password' => 'رمز عبور'];
@endphp
@foreach ($fields as $name => $label)
    @php
        $password = str_starts_with($name, 'password');
        $id = $prefix.'-'.$name;
        $fieldError = $modal ? null : $errors->first($name);
        $fieldValue = $modal ? '' : old($name, '');
        $autocomplete = match ($name) {
            'mobile' => $registration ? 'tel' : 'username',
            'password' => $registration ? 'new-password' : 'current-password',
            'password_confirmation' => 'new-password',
            default => $name,
        };
    @endphp
    <div class="portal-field">
        <label for="{{ $id }}">{{ $label }}</label>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $password ? 'password' : ($name === 'email' ? 'email' : ($name === 'mobile' ? 'tel' : 'text')) }}"
            @unless ($password) value="{{ is_string($fieldValue) ? $fieldValue : '' }}" maxlength="{{ $name === 'mobile' ? 32 : 255 }}" @endunless
            @if ($password && $registration) maxlength="255" @endif
            @if ($registration && $password) minlength="8" @endif
            @if ($name === 'mobile') inputmode="tel" @endif
            @unless ($name === 'name') dir="ltr" @endunless
            autocomplete="{{ $autocomplete }}"
            aria-describedby="{{ $id }}-error"
            @if ($fieldError) aria-invalid="true" @endif
            @unless ($name === 'email') required @endunless
            @if ($loop->first && ! $modal) autofocus @endif
            @if ($loop->first && $modal) data-header-overlay-autofocus @endif
        >
        <span class="portal-error" id="{{ $id }}-error" data-auth-field-error @unless ($fieldError) hidden @endunless>{{ $fieldError }}</span>
    </div>
@endforeach
