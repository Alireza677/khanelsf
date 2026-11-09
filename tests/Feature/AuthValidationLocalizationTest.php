<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthValidationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private const DUPLICATE_EMAIL = 'این ایمیل قبلاً ثبت شده است. لطفاً از ایمیل دیگری استفاده کنید یا وارد حساب خود شوید.';

    private const DUPLICATE_MOBILE = 'این شماره موبایل قبلاً ثبت شده است. لطفاً از شماره دیگری استفاده کنید یا وارد حساب خود شوید.';

    public function test_persian_validation_uses_the_existing_application_language_path(): void
    {
        $this->assertSame('fa', app()->getLocale());
        $this->assertSame('fa', config('app.fallback_locale'));
        $this->assertSame(resource_path('lang'), app()->langPath());
        foreach (['required', 'string', 'confirmed', 'unique', 'email', 'min.string', 'max.string'] as $rule) {
            $this->assertNotSame('validation.'.$rule, __('validation.'.$rule));
        }
    }

    public function test_duplicate_email_message_is_exact_and_identical_in_json_session_and_rendered_page(): void
    {
        User::factory()->client()->create(['email' => 'used@example.com']);
        $this->assertRegistrationError(['email' => 'used@example.com'], 'email', self::DUPLICATE_EMAIL);
    }

    public function test_duplicate_normalized_mobile_has_a_specific_message_in_both_transports(): void
    {
        User::factory()->client()->create(['mobile' => '09121111111']);
        $this->assertRegistrationError(['mobile' => '+98 912 111 1111'], 'mobile', self::DUPLICATE_MOBILE);
    }

    #[DataProvider('registrationErrors')]
    public function test_registration_errors_are_persian_and_identical_without_javascript(array $overrides, string $field, string $expected): void
    {
        $this->assertRegistrationError($overrides, $field, $expected);
    }

    public static function registrationErrors(): array
    {
        return [
            'required name' => [['name' => ''], 'name', 'وارد کردن نام الزامی است.'],
            'required mobile' => [['mobile' => ''], 'mobile', 'وارد کردن شماره موبایل الزامی است.'],
            'invalid normalized mobile' => [['mobile' => 'abc'], 'mobile', 'وارد کردن شماره موبایل الزامی است.'],
            'required password' => [['password' => ''], 'password', 'وارد کردن رمز عبور الزامی است.'],
            'required confirmation' => [['password_confirmation' => ''], 'password_confirmation', 'وارد کردن تکرار رمز عبور الزامی است.'],
            'invalid email' => [['email' => 'invalid-email'], 'email', 'ایمیل باید یک ایمیل معتبر باشد.'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password', 'رمز عبور باید حداقل 8 کاراکتر باشد.'],
            'mismatched confirmation' => [['password_confirmation' => 'different-password'], 'password', 'تکرار رمز عبور با رمز عبور واردشده مطابقت ندارد.'],
            'long name' => [['name' => str_repeat('ن', 256)], 'name', 'نام نباید بیشتر از 255 کاراکتر باشد.'],
            'long mobile' => [['mobile' => '09'.str_repeat('1', 31)], 'mobile', 'شماره موبایل نباید بیشتر از 32 کاراکتر باشد.'],
            'long email' => [['email' => str_repeat('a', 244).'@example.com'], 'email', 'ایمیل نباید بیشتر از 255 کاراکتر باشد.'],
            'long password' => [['password' => str_repeat('a', 256), 'password_confirmation' => str_repeat('a', 256)], 'password', 'رمز عبور نباید بیشتر از 255 کاراکتر باشد.'],
            'non-text name' => [['name' => ['invalid']], 'name', 'مقدار نام باید متن باشد.'],
            'non-text password' => [['password' => ['invalid'], 'password_confirmation' => ['invalid']], 'password', 'مقدار رمز عبور باید متن باشد.'],
            'non-text confirmation' => [['password_confirmation' => ['invalid']], 'password_confirmation', 'مقدار تکرار رمز عبور باید متن باشد.'],
        ];
    }

    #[DataProvider('loginErrors')]
    public function test_login_validation_and_wrong_credentials_are_identical_in_both_transports(array $payload, string $field, string $expected): void
    {
        $json = $this->postJson(route('client.login.store'), $payload)->assertUnprocessable();
        $json->assertJsonPath('errors.'.$field.'.0', $expected);
        $this->assertPersianMessages($json->json());
        $this->from(route('login'))->post(route('client.login.store'), $payload)
            ->assertRedirect(route('login'))->assertSessionHasErrors([$field => $expected]);
        $this->get(route('login'))->assertOk()->assertSee($expected)->assertDontSee('validation.');
        $this->assertGuest('client');
        $this->assertDatabaseCount('orders', 0);
    }

    public static function loginErrors(): array
    {
        return [
            'required mobile' => [['mobile' => '', 'password' => 'password'], 'mobile', 'وارد کردن شماره موبایل الزامی است.'],
            'required password' => [['mobile' => '09122222222', 'password' => ''], 'password', 'وارد کردن رمز عبور الزامی است.'],
            'wrong credentials' => [['mobile' => '09122222222', 'password' => 'wrong-password'], 'mobile', 'شماره موبایل یا رمز عبور صحیح نیست، یا حساب شما فعال نیست.'],
        ];
    }

    public function test_multiple_error_summary_and_every_field_message_are_persian(): void
    {
        $json = $this->postJson(route('client.register.store'), [])->assertUnprocessable();
        $this->assertPersianMessages($json->json());
        $this->assertStringNotContainsString('and ', $json->json('message'));
        $this->assertStringNotContainsString('more error', $json->json('message'));
        $this->assertStringContainsString('خطای دیگر', $json->json('message'));
    }

    #[DataProvider('authEndpoints')]
    public function test_rate_limit_message_is_the_same_persian_text_for_modal_and_page(string $endpoint): void
    {
        $expected = 'تعداد تلاش‌ها زیاد است. کمی صبر کنید و دوباره تلاش کنید.';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route($endpoint), [])->assertUnprocessable();
        }
        $this->postJson(route($endpoint), [])->assertStatus(429)->assertHeader('Retry-After')
            ->assertJsonPath('code', 'rate_limited')->assertJsonPath('message', $expected);
        $this->post(route($endpoint), [])->assertStatus(429)->assertHeader('Retry-After')->assertSee($expected)
            ->assertDontSee('Too Many Requests');
        $this->assertGuest('client');
    }

    public static function authEndpoints(): array
    {
        return [['client.login.store'], ['client.register.store']];
    }

    public function test_shared_unique_translation_is_generic_outside_public_auth(): void
    {
        User::factory()->client()->create(['email' => 'used@example.com']);
        $validator = Validator::make(['email' => 'used@example.com'], ['email' => 'unique:users,email']);
        $this->assertSame('ایمیل قبلاً استفاده شده است.', $validator->errors()->first('email'));
    }

    private function assertRegistrationError(array $overrides, string $field, string $expected): void
    {
        $usersBefore = User::query()->count();
        $payload = [...$this->registration(), ...$overrides];
        $json = $this->postJson(route('client.register.store'), $payload)->assertUnprocessable();
        $json->assertJsonPath('errors.'.$field.'.0', $expected);
        $this->assertPersianMessages($json->json());
        $this->from(route('register'))->post(route('client.register.store'), $payload)
            ->assertRedirect(route('register'))->assertSessionHasErrors([$field => $expected]);
        $this->get(route('register'))->assertOk()->assertSee($expected)->assertDontSee('validation.');
        $this->assertDatabaseCount('users', $usersBefore);
        $this->assertGuest('client');
        $this->assertDatabaseCount('orders', 0);
    }

    private function assertPersianMessages(array $json): void
    {
        foreach ([$json['message'], ...collect($json['errors'])->flatten()->all()] as $message) {
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $message);
            $this->assertDoesNotMatchRegularExpression('/(?:validation|auth)\.[a-z_.]+/', $message);
        }
    }

    private function registration(): array
    {
        return ['name' => 'کاربر جدید', 'mobile' => '09122222222', 'email' => 'new@example.com',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password'];
    }
}
