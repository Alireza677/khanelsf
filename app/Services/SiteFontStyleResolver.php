<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteFontStyleResolver
{
    public const FALLBACK_FAMILY = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

    public const BUNDLED_FAMILY = 'CMS Default Persian';

    public const BUNDLED_FILENAME = 'cms-default-persian.woff2';

    private static bool $missingBundledFontWasLogged = false;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array{
     *     mode: string,
     *     name: ?string,
     *     path: ?string,
     *     url: ?string,
     *     format: ?string,
     *     uploaded_valid: bool,
     *     bundled_family: ?string,
     *     bundled_url: ?string,
     *     bundled_available: bool,
     *     family: string
     * }
     */
    public function resolve(): array
    {
        $mode = $this->mode();
        $name = $this->customName();
        $source = $mode === 'custom' ? $this->customSource() : null;
        $bundled = $this->bundledSource();
        $path = $source['path'] ?? null;
        $url = $source['url'] ?? null;
        $format = $source['format'] ?? null;
        $uploadedValid = $mode === 'custom' && $name && $url && $format;
        $bundledFamily = $bundled['family'] ?? null;
        $bundledUrl = $bundled['url'] ?? null;
        $families = [];

        if ($uploadedValid) {
            $families[] = '"'.$this->escapeCssString($name).'"';
        }

        if ($bundledFamily) {
            $families[] = '"'.$this->escapeCssString($bundledFamily).'"';
        }

        $families[] = self::FALLBACK_FAMILY;

        return compact('mode', 'name', 'path', 'url', 'format') + [
            'uploaded_valid' => (bool) $uploadedValid,
            'bundled_family' => $bundledFamily,
            'bundled_url' => $bundledUrl,
            'bundled_available' => $bundled !== null,
            'family' => implode(', ', $families),
        ];
    }

    public function css(): string
    {
        $font = $this->resolve();
        $declarations = '';

        if ($font['bundled_available'] && $font['bundled_family'] && $font['bundled_url']) {
            $declarations .= $this->fontFace(
                $font['bundled_family'],
                $font['bundled_url'],
                'woff2',
            );
        }

        if ($font['uploaded_valid'] && $font['name'] && $font['url'] && $font['format']) {
            $declarations .= $this->fontFace(
                $font['name'],
                $font['url'],
                $font['format'],
            );
        }

        return $declarations.':root{--site-font-family:'.$font['family'].';--theme-font-family:var(--site-font-family);}';
    }

    /**
     * @return array{family: string, path: string, url: string}|null
     */
    private function bundledSource(): ?array
    {
        $family = $this->safeFamilyName(config('cms.default_font.family', self::BUNDLED_FAMILY));
        $filename = (string) config('cms.default_font.filename', self::BUNDLED_FILENAME);

        if ($family === null
            || $filename !== basename($filename)
            || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'woff2'
            || preg_match('/^[a-z0-9][a-z0-9._-]*\.woff2$/', $filename) !== 1
        ) {
            return null;
        }

        $path = public_path('fonts/'.$filename);

        if (! is_file($path) || filesize($path) < 1) {
            $this->logMissingBundledFont($path);

            return null;
        }

        return [
            'family' => $family,
            'path' => $path,
            'url' => '/fonts/'.$filename,
        ];
    }

    private function mode(): string
    {
        $value = $this->firstFilledSetting(['font_family', 'font_mode', 'site_font_family'], 'system');
        $value = strtolower(trim((string) $value));

        return match ($value) {
            'custom', 'uploaded', 'upload', 'custom_font' => 'custom',
            'serif' => 'serif',
            'mono', 'monospace' => 'mono',
            default => 'system',
        };
    }

    private function customName(): ?string
    {
        return $this->safeFamilyName(
            $this->firstFilledSetting(['custom_font_name', 'font_name'], ''),
        );
    }

    /**
     * @return array{path: string, url: string, format: string}|null
     */
    private function customSource(): ?array
    {
        $value = $this->firstFilledSetting(['custom_font_file', 'custom_font_path', 'font_file']);
        $value = $this->unwrapStoredFileValue($value);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && Str::startsWith($value, $appUrl.'/')) {
            $value = Str::after($value, $appUrl);
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $format = $this->format($value);
            $urlPath = rawurldecode((string) parse_url($value, PHP_URL_PATH));

            if (! $format || ! filter_var($value, FILTER_VALIDATE_URL) || $this->hasUnsafePath($urlPath)) {
                return null;
            }

            return [
                'path' => $value,
                'url' => $value,
                'format' => $format,
            ];
        }

        $value = ltrim($value, '/');

        if (Str::startsWith($value, 'storage/')) {
            $value = Str::after($value, 'storage/');
        }

        $format = $this->format($value);

        if ($value === '' || ! $format || $this->hasUnsafePath(rawurldecode($value))) {
            return null;
        }

        if (! Storage::disk('public')->exists($value)) {
            return null;
        }

        return [
            'path' => $value,
            'url' => '/storage/'.$value,
            'format' => $format,
        ];
    }

    private function unwrapStoredFileValue(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach (['path', 'file', 'url', 0] as $key) {
                if (array_key_exists($key, $value)) {
                    return $this->unwrapStoredFileValue($value[$key]);
                }
            }

            return null;
        }

        if (is_string($value) && Str::startsWith(trim($value), ['[', '{'])) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->unwrapStoredFileValue($decoded);
            }
        }

        return $value;
    }

    private function format(string $path): ?string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => null,
        };
    }

    private function hasUnsafePath(string $path): bool
    {
        return str_contains($path, "\0")
            || preg_match('~(?:^|/)\.\.(?:/|$)~', str_replace('\\', '/', $path)) === 1;
    }

    private function firstFilledSetting(array $keys, mixed $fallback = null): mixed
    {
        foreach ($keys as $key) {
            $value = $this->settings->get($key);

            if (filled($value)) {
                return $value;
            }
        }

        return $fallback;
    }

    private function fontFace(string $family, string $url, string $format): string
    {
        return sprintf(
            '@font-face{font-family:"%s";src:url("%s") format("%s");font-display:swap;font-style:normal;font-weight:100 900;}',
            $this->escapeCssString($family),
            $this->escapeCssString($url),
            $format,
        );
    }

    private function safeFamilyName(mixed $value): ?string
    {
        $name = trim((string) $value);

        if ($name === '') {
            return null;
        }

        $name = preg_replace('/[^\pL\pN\s_-]/u', '', $name);
        $name = is_string($name) ? trim(Str::limit($name, 80, '')) : '';

        return $name !== '' ? $name : null;
    }

    private function logMissingBundledFont(string $path): void
    {
        if (! app()->isLocal() || self::$missingBundledFontWasLogged) {
            return;
        }

        self::$missingBundledFontWasLogged = true;

        Log::warning('Bundled CMS default font is unavailable; using the system font fallback.', [
            'expected_path' => $path,
        ]);
    }

    private function escapeCssString(string $value): string
    {
        return str_replace(
            ['\\', '"', "\r", "\n", "\f"],
            ['\\\\', '\\"', '', '', ''],
            $value,
        );
    }
}
