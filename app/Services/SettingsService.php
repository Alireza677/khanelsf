<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsService
{
    protected static array $requestCache = [];

    public function get(string $key, mixed $fallback = null): mixed
    {
        if (! static::requestCacheEnabled()) {
            return rescue(
                fn () => Setting::query()->where('key', $key)->value('value') ?? $fallback,
                $fallback,
                report: false,
            );
        }

        if (array_key_exists($key, static::$requestCache)) {
            return static::$requestCache[$key] ?? $fallback;
        }

        return rescue(
            fn () => static::$requestCache[$key] = Setting::query()->where('key', $key)->value('value') ?? $fallback,
            $fallback,
            report: false,
        );
    }

    public function many(array $keys): Collection
    {
        if (! static::requestCacheEnabled()) {
            return rescue(
                fn () => Setting::query()
                    ->whereIn('key', $keys)
                    ->pluck('value', 'key'),
                collect(),
                report: false,
            );
        }

        $keys = array_values(array_unique($keys));
        $missingKeys = array_values(array_filter(
            $keys,
            fn (string $key): bool => ! array_key_exists($key, static::$requestCache),
        ));

        if ($missingKeys === []) {
            return collect($keys)
                ->mapWithKeys(fn (string $key): array => [$key => static::$requestCache[$key] ?? null]);
        }

        return rescue(
            function () use ($keys, $missingKeys): Collection {
                Setting::query()
                    ->whereIn('key', $missingKeys)
                    ->pluck('value', 'key')
                    ->each(fn (mixed $value, string $key) => static::$requestCache[$key] = $value);

                foreach ($missingKeys as $key) {
                    static::$requestCache[$key] ??= null;
                }

                return collect($keys)
                    ->mapWithKeys(fn (string $key): array => [$key => static::$requestCache[$key] ?? null]);
            },
            collect($keys)->mapWithKeys(fn (string $key): array => [$key => static::$requestCache[$key] ?? null]),
            report: false,
        );
    }

    public function set(string $key, mixed $value, ?string $group = null, string $type = 'text'): Setting
    {
        unset(static::$requestCache[$key]);

        return Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                'group' => $group,
                'type' => $type,
            ],
        );
    }

    protected static function requestCacheEnabled(): bool
    {
        return ! app()->runningUnitTests();
    }

    public function siteName(): string
    {
        return (string) ($this->get('site_name') ?: config('app.name'));
    }

    public function siteTitle(): string
    {
        return (string) ($this->get('site_title') ?: $this->siteName());
    }

    public function defaultMetaDescription(): string
    {
        return (string) ($this->get('default_meta_description') ?: '');
    }

    public function googleSiteVerification(): ?string
    {
        return $this->nullableString('google_site_verification');
    }

    public function siteDescription(): ?string
    {
        return $this->nullableString('site_description');
    }

    public function faviconUrl(): ?string
    {
        return $this->assetUrl($this->get('site_favicon'));
    }

    public function logoUrl(): ?string
    {
        $logoValue = $this->get('site_logo') ?: $this->get('logo_path');

        if (blank($logoValue)) {
            return null;
        }

        $logoValue = (string) $logoValue;

        return $this->assetUrl($logoValue);
    }

    public function imagePlaceholderUrl(): ?string
    {
        return $this->assetUrl($this->get('image_placeholder'));
    }

    public function headerCtaLabel(): ?string
    {
        return $this->nullableString('header_cta_label', 'تماس با ما');
    }

    public function headerCtaUrl(): ?string
    {
        return $this->nullableString('header_cta_url', route('contact.create', absolute: false));
    }

    public function contactEmail(): ?string
    {
        return $this->nullableString('contact_email');
    }

    public function contactPhone(): ?string
    {
        return $this->nullableString('contact_phone');
    }

    public function contactAddress(): ?string
    {
        return $this->nullableString('contact_address');
    }

    public function footerText(): ?string
    {
        return $this->nullableString('footer_text') ?: $this->siteDescription();
    }

    public function socialLinks(): array
    {
        $links = [
            'instagram' => ['label' => 'Instagram', 'url' => $this->nullableString('social_instagram_url')],
            'telegram' => ['label' => 'Telegram', 'url' => $this->nullableString('social_telegram_url')],
            'whatsapp' => ['label' => 'WhatsApp', 'url' => $this->nullableString('social_whatsapp_url')],
            'linkedin' => ['label' => 'LinkedIn', 'url' => $this->nullableString('social_linkedin_url')],
            'x' => ['label' => 'X', 'url' => $this->nullableString('social_x_url')],
        ];

        return array_filter($links, fn (array $link): bool => filled($link['url']));
    }

    public function themeVariables(): array
    {
        $startedAt = hrtime(true);

        $this->many([
            'primary_color',
            'secondary_color',
            'accent_color',
            'text_color',
            'link_color',
            'background_color',
            'button_radius',
            'container_width',
            'font_family',
            'custom_font_file',
            'custom_font_name',
            'base_font_size',
            'h1_font_size',
            'h2_font_size',
            'h3_font_size',
            'h4_font_size',
            'button_font_size',
            'base_font_size_mobile',
            'h1_font_size_mobile',
            'h2_font_size_mobile',
            'h3_font_size_mobile',
            'h4_font_size_mobile',
            'button_font_size_mobile',
            'button_radius_mobile',
            'container_width_mobile',
        ]);

        $variables = [
            '--theme-primary' => $this->color('primary_color', '#2563eb'),
            '--theme-primary-hover' => $this->color('primary_color', '#1d4ed8'),
            '--theme-secondary' => $this->color('secondary_color', '#111827'),
            '--theme-accent' => $this->color('accent_color', '#0f766e'),
            '--theme-text' => $this->color('text_color', '#1f2937'),
            '--theme-link' => $this->color('link_color', '#2563eb'),
            '--theme-heading' => $this->color('secondary_color', '#111827'),
            '--theme-background' => $this->color('background_color', '#f8fafc'),
            '--theme-button-radius' => $this->cssLength('button_radius', '10px'),
            '--theme-container-width' => $this->cssLength('container_width', '1200px'),
            '--theme-font-family' => $this->fontFamily(),
            '--theme-base-font-size' => $this->cssLength('base_font_size', '16px'),
            '--theme-h1-font-size' => $this->cssLength('h1_font_size', '24px'),
            '--theme-h2-font-size' => $this->cssLength('h2_font_size', '22px'),
            '--theme-h3-font-size' => $this->cssLength('h3_font_size', '20px'),
            '--theme-h4-font-size' => $this->cssLength('h4_font_size', '18px'),
            '--theme-button-font-size' => $this->cssLength('button_font_size', '16px'),
            '--theme-base-font-size-mobile' => $this->cssLength('base_font_size_mobile', '15px'),
            '--theme-h1-font-size-mobile' => $this->cssLength('h1_font_size_mobile', '22px'),
            '--theme-h2-font-size-mobile' => $this->cssLength('h2_font_size_mobile', '20px'),
            '--theme-h3-font-size-mobile' => $this->cssLength('h3_font_size_mobile', '18px'),
            '--theme-h4-font-size-mobile' => $this->cssLength('h4_font_size_mobile', '16px'),
            '--theme-button-font-size-mobile' => $this->cssLength('button_font_size_mobile', '15px'),
            '--theme-button-radius-mobile' => $this->cssLength('button_radius_mobile', '10px'),
            '--theme-container-width-mobile' => $this->cssLength('container_width_mobile', '343px'),
        ];

        if (request()->routeIs('filament.admin.resources.pages.edit')) {
            Log::info('PERF PageResource edit: settings load ms', [
                'ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
                'keys' => count($variables),
            ]);
        }

        return $variables;
    }

    public function customFontUrl(): ?string
    {
        if ((string) $this->get('font_family', 'system') !== 'custom') {
            return null;
        }

        return $this->assetUrl($this->get('custom_font_file'));
    }

    public function customFontName(): string
    {
        $name = trim((string) $this->get('custom_font_name', 'Client Custom Font'));
        $name = preg_replace('/[^\pL\pN\s_-]/u', '', $name) ?: 'Client Custom Font';

        return Str::limit($name, 80, '');
    }

    public function customFontFormat(): string
    {
        $path = strtolower((string) $this->get('custom_font_file', ''));

        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => 'woff2',
        };
    }

    private function nullableString(string $key, ?string $fallback = null): ?string
    {
        $value = $this->get($key, $fallback);

        return filled($value) ? (string) $value : null;
    }

    public function assetUrl(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = (string) $value;

        if (Str::startsWith($value, ['http://', 'https://', '/'])) {
            return $value;
        }

        if (Storage::disk('public')->exists($value)) {
            return Storage::disk('public')->url($value);
        }

        return asset($value);
    }

    private function color(string $key, string $fallback): string
    {
        $value = (string) $this->get($key, $fallback);

        return preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value) ? $value : $fallback;
    }

    private function cssLength(string $key, string $fallback): string
    {
        $value = trim((string) $this->get($key, $fallback));

        return preg_match('/^(?:\d{1,4}(?:\.\d{1,2})?|0?\.\d{1,2})(px|rem|em|%|vw)$/', $value) ? $value : $fallback;
    }

    private function fontFamily(): string
    {
        if ((string) $this->get('font_family', 'system') === 'custom' && filled($this->customFontUrl())) {
            return '"'.$this->customFontName().'", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        }

        return match ((string) $this->get('font_family', 'system')) {
            'serif' => 'Georgia, Cambria, "Times New Roman", Times, serif',
            'mono' => '"SFMono-Regular", Consolas, "Liberation Mono", monospace',
            default => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        };
    }
}
