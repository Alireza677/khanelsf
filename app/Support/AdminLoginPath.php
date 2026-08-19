<?php

namespace App\Support;

use App\Models\Page;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginPath
{
    public const DEFAULT = 'admin/login';

    public function __construct(private readonly SettingsService $settings) {}

    public function current(): string
    {
        $value = $this->normalize($this->settings->get('admin_login_path', self::DEFAULT));

        return $this->isSyntacticallyValid($value) && ! $this->collides($value) ? $value : self::DEFAULT;
    }

    public function url(bool $absolute = true): string
    {
        return url($this->current(), [], null, $absolute);
    }

    public function normalize(mixed $value): string
    {
        return trim(trim((string) $value), '/');
    }

    public function validate(mixed $value): string
    {
        $path = $this->normalize($value);

        if (! $this->isSyntacticallyValid($path)) {
            throw ValidationException::withMessages([
                'data.admin_login_path' => 'آدرس باید یک مسیر نسبی معتبر مانند secure-admin یا management/access باشد.',
            ]);
        }

        if ($this->collides($path)) {
            throw ValidationException::withMessages([
                'data.admin_login_path' => 'این آدرس قبلاً توسط بخش دیگری از سایت استفاده شده است. لطفاً آدرس دیگری انتخاب کنید.',
            ]);
        }

        return $path;
    }

    private function isSyntacticallyValid(string $path): bool
    {
        if ($path === '' || strlen($path) > 190 || Str::contains($path, ['://', '//', '?', '#', '\\', '..'])) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(?:\/[A-Za-z0-9][A-Za-z0-9_-]*)*$/', $path);
    }

    private function collides(string $path): bool
    {
        if ($path === self::DEFAULT) {
            return false;
        }

        $firstSegment = Str::before($path, '/');
        $publicTarget = public_path($firstSegment);

        if (file_exists($publicTarget) || in_array($firstSegment, ['api', 'livewire', 'admin'], true)) {
            return true;
        }

        if (! str_contains($path, '/') && Page::query()->where('slug', $path)->exists()) {
            return true;
        }

        $request = Request::create('/'.$path, 'GET');

        return collect(Route::getRoutes()->getRoutes())->contains(function (IlluminateRoute $route) use ($request): bool {
            if (in_array($route->getName(), ['admin-login.dynamic', 'pages.show', 'filament.admin.auth.login'], true)) {
                return false;
            }

            return $route->matches($request, true);
        });
    }
}
