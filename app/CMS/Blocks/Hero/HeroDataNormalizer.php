<?php

namespace App\CMS\Blocks\Hero;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\CTA\CTALegacyActionAdapter;
use App\CMS\Blocks\Support\HeadingLevel;
use Illuminate\Support\Arr;

final class HeroDataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION = 2;

    public function __construct(
        private readonly HeroMediaResolver $mediaResolver,
        private readonly ?CTALegacyActionAdapter $legacyActionAdapter = null,
    ) {}

    public function isLegacy(array $data): bool
    {
        return (int) ($data['schema_version'] ?? 1) < self::SCHEMA_VERSION;
    }

    public function currentSchemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function normalize(array $data): array
    {
        if (! $this->isLegacy($data)) {
            return $this->normalizeV2($data);
        }

        $template = is_string($data['template'] ?? null) ? $data['template'] : 'default';
        $imageUrl = $this->stringOrNull($data['image'] ?? null);
        $videoUrl = $this->stringOrNull($data['hero_2_video_url'] ?? null);
        $posterUrl = $this->stringOrNull($data['hero_2_video_poster'] ?? null);
        $kind = $template === 'hero_2' && (($data['hero_2_background_type'] ?? null) === 'video' || $videoUrl !== null)
            ? 'video'
            : 'image';

        $normalized = [
            'block_id' => $this->stringOrNull($data['block_id'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
            'template' => $template,
            'content' => [
                'eyebrow' => [
                    'text' => $this->valueOrNull($data, 'eyebrow'),
                    'icon' => $this->valueOrNull($data, 'hero_1_eyebrow_icon'),
                ],
                'title' => $this->valueOrNull($data, 'title'),
                'title_secondary' => $this->valueOrNull($data, 'hero_1_title_second_line'),
                'lead' => $this->valueOrNull($data, 'subtitle'),
                'description' => $this->valueOrNull($data, 'description'),
                'media' => [
                    'kind' => $kind,
                    'source_id' => $this->mediaResolver->resolveSourceId($imageUrl),
                    'url' => $imageUrl,
                    'alt' => null,
                    'video_url' => $videoUrl,
                    'poster_source_id' => $this->mediaResolver->resolveSourceId($posterUrl),
                    'poster_url' => $posterUrl,
                ],
                'primary_cta' => $this->legacyCta($data, 'primary_button_label', 'primary_button_url'),
                'secondary_cta' => $this->legacyCta($data, 'secondary_button_label', 'secondary_button_url'),
                'selector' => $this->selector($data),
                'stats' => $this->arrayOrEmpty($data['stats'] ?? null),
                'social_links' => $this->canonicalActionItems($this->arrayOrEmpty($data['hero_1_social_links'] ?? null)),
                'scroll_label' => $this->valueOrNull($data, 'hero_1_scroll_label'),
            ],
            'settings' => [
                'heading_tag' => HeadingLevel::normalize($data['heading_tag'] ?? null),
                'alignment' => $this->alignment($data, $template),
                'height' => $this->height($data, $template),
                'color_mode' => $this->stringOrDefault($data['section_background'] ?? null, 'default'),
                'background_treatment' => $this->backgroundTreatment($data, $template),
                'overlay_opacity' => $this->clampedNumber($data['overlay_opacity'] ?? null, 45, 0, 90),
                'media' => [
                    'desktop' => $this->responsiveMedia($data, 'image'),
                    'mobile' => $this->responsiveMedia($data, 'image_mobile'),
                ],
                'background_effect' => $this->backgroundEffect($data),
                'title_decoration' => filter_var($data['hero_1_show_underline'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'underline' : 'none',
                'eyebrow_icon_size' => $this->valueOrNull($data, 'hero_1_eyebrow_icon_size'),
            ],
        ];

        $this->inferHeroOneFeatures($normalized, []);

        return $normalized;
    }

    private function normalizeV2(array $data): array
    {
        $normalized = array_replace_recursive($this->emptyContract(), $data);
        $normalized['schema_version'] = self::SCHEMA_VERSION;
        $normalized['block_id'] = $this->stringOrNull($normalized['block_id']);
        $normalized['settings']['heading_tag'] = HeadingLevel::normalize(
            $normalized['settings']['heading_tag'] ?? null,
        );
        $normalized['settings']['background_treatment'] = $this->stringOrDefault(
            $normalized['settings']['background_treatment'] ?? null,
            'image',
        );
        $normalized['content']['primary_cta'] = $this->canonicalCta($data, 'primary_cta', $normalized['content']['primary_cta']);
        $normalized['content']['secondary_cta'] = $this->canonicalCta($data, 'secondary_cta', $normalized['content']['secondary_cta']);
        $normalized['content']['selector'] = $this->canonicalSelector($normalized['content']['selector']);
        $normalized['content']['social_links'] = $this->canonicalActionItems($normalized['content']['social_links']);
        $this->inferHeroOneFeatures($normalized, $data);

        return $normalized;
    }

    private function emptyContract(): array
    {
        return [
            'block_id' => null,
            'schema_version' => self::SCHEMA_VERSION,
            'template' => 'default',
            'content' => [
                'eyebrow' => ['text' => null, 'icon' => null],
                'title' => null, 'title_secondary' => null, 'lead' => null, 'description' => null,
                'media' => ['kind' => 'image', 'source_id' => null, 'url' => null, 'alt' => null, 'video_url' => null, 'poster_source_id' => null, 'poster_url' => null],
                'primary_cta' => ['enabled' => false, 'label' => null, 'action' => null],
                'secondary_cta' => ['enabled' => false, 'label' => null, 'action' => null],
                'selector' => null, 'stats' => [], 'social_links' => [], 'scroll_label' => null,
            ],
            'settings' => [
                'heading_tag' => 'h2', 'alignment' => 'start',
                'height' => ['desktop' => null, 'mobile' => null],
                'color_mode' => 'default', 'background_treatment' => 'image', 'overlay_opacity' => 45,
                'media' => ['desktop' => $this->emptyResponsiveMedia(), 'mobile' => $this->emptyResponsiveMedia()],
                'background_effect' => ['type' => 'none', 'enabled' => true, 'interactive' => true, 'speed' => 'slow', 'density' => 'medium', 'opacity' => 0.45, 'background_color_override' => null, 'foreground_color_override' => null, 'settings' => []],
                'title_decoration' => 'none', 'eyebrow_icon_size' => null,
            ],
        ];
    }

    private function legacyCta(array $data, string $labelKey, string $urlKey): array
    {
        $label = $this->valueOrNull($data, $labelKey);
        $action = $this->adaptAction(['url' => $this->valueOrNull($data, $urlKey)]);

        return ['enabled' => filled($label) || $action !== null, 'label' => $label, 'action' => $action];
    }

    private function canonicalCta(array $source, string $name, array $cta): array
    {
        $action = data_get($source, "content.{$name}.action");

        if (! is_array($action)) {
            $action = $this->adaptAction(['url' => data_get($source, "content.{$name}.url")]);
        } else {
            $action = $this->adaptAction($action);
        }

        return ['enabled' => (bool) ($cta['enabled'] ?? false), 'label' => $this->stringOrNull($cta['label'] ?? null), 'action' => $action];
    }

    private function adaptAction(array $data): ?array
    {
        $destination = ($this->legacyActionAdapter ?? app(CTALegacyActionAdapter::class))->adapt($data);

        return $destination->type === null ? null : $destination->toArray();
    }

    private function inferHeroOneFeatures(array &$normalized, array $source): void
    {
        if (($normalized['template'] ?? null) !== 'hero_1') {
            return;
        }

        $content = &$normalized['content'];
        if (! Arr::has($source, 'content.eyebrow.enabled')) {
            $content['eyebrow']['enabled'] = filled($content['eyebrow']['text'] ?? null) || filled($content['eyebrow']['icon'] ?? null);
        }
        if (! Arr::has($source, 'content.title_secondary_enabled')) {
            $content['title_secondary_enabled'] = filled($content['title_secondary'] ?? null)
                || ($normalized['settings']['title_decoration'] ?? 'none') === 'underline';
        }
        if (! Arr::has($source, 'content.title_secondary_underline')) {
            $content['title_secondary_underline'] = ($normalized['settings']['title_decoration'] ?? 'none') === 'underline';
        }

        foreach (['primary_cta', 'secondary_cta'] as $name) {
            if (! Arr::has($source, "content.{$name}.enabled")) {
                $content[$name]['enabled'] = filled($content[$name]['label'] ?? null) || is_array($content[$name]['action'] ?? null);
            }
        }
        if (! Arr::has($source, 'content.ctas_enabled')) {
            $content['ctas_enabled'] = $content['primary_cta']['enabled'] || $content['secondary_cta']['enabled'];
        }
    }

    private function selector(array $data): ?array
    {
        $items = $this->arrayOrEmpty($data['selector_items'] ?? null);
        $placeholder = $this->valueOrNull($data, 'selector_placeholder');
        $defaultIndex = $this->selectorDefaultIndex($data['selector_default_index'] ?? null, count($items));

        return $items === [] && $placeholder === null ? null : ['placeholder' => $placeholder, 'default_index' => $defaultIndex, 'items' => $this->canonicalActionItems($items)];
    }

    private function canonicalSelector(mixed $selector): ?array
    {
        if (! is_array($selector)) {
            return null;
        }

        return [
            'placeholder' => $this->stringOrNull($selector['placeholder'] ?? null),
            'default_index' => $this->selectorDefaultIndex($selector['default_index'] ?? null, count($selector['items'] ?? [])),
            'items' => $this->canonicalActionItems($this->arrayOrEmpty($selector['items'] ?? null)),
        ];
    }

    private function selectorDefaultIndex(mixed $value, int $itemCount): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $index = (int) $value;

        return $index >= 0 && $index < $itemCount ? $index : null;
    }

    /** @param array<int, mixed> $items @return array<int, array<string, mixed>> */
    private function canonicalActionItems(array $items): array
    {
        return collect($items)->filter(fn (mixed $item): bool => is_array($item))->map(function (array $item): array {
            $action = is_array($item['action'] ?? null)
                ? $this->adaptAction($item['action'])
                : $this->adaptAction(['url' => $this->firstPresent($item, ['url', 'link', 'button_url', 'cta_url'])]);

            unset($item['url'], $item['link'], $item['button_url'], $item['cta_url']);
            $item['label'] = $this->stringOrNull($item['label'] ?? null);
            $item['action'] = $action;

            return $item;
        })->values()->all();
    }

    private function alignment(array $data, string $template): string
    {
        $value = match ($template) {
            'hero_2' => $data['hero_2_alignment'] ?? null,
            'hero_3' => $data['hero_3_alignment'] ?? null,
            default => $data['alignment'] ?? null,
        };

        return $this->stringOrDefault($value, match ($template) {
            'hero_2' => 'left', 'hero_3' => 'right', default => 'left',
        });
    }

    private function height(array $data, string $template): array
    {
        return match ($template) {
            'hero_1' => [
                'desktop' => $this->nonNegativeNumber($this->firstPresent($data, ['hero_1_desktop_height', 'hero_1_height'])),
                'mobile' => $this->nonNegativeNumber($this->valueOrNull($data, 'hero_1_mobile_height')),
            ],
            'hero_2' => ['desktop' => $this->nonNegativeNumber($this->valueOrNull($data, 'hero_2_height')), 'mobile' => null],
            default => ['desktop' => null, 'mobile' => null],
        };
    }

    private function backgroundTreatment(array $data, string $template): string
    {
        return match ($template) {
            'hero_1' => $this->stringOrDefault($data['hero_1_theme'] ?? null, 'image'),
            'hero_2' => $this->stringOrDefault($data['hero_2_background_type'] ?? null, filled($data['hero_2_video_url'] ?? null) ? 'video' : 'image'),
            default => 'image',
        };
    }

    private function backgroundEffect(array $data): array
    {
        $theme = $data['hero_1_theme'] ?? null;

        if ($theme === 'animated_dotted_surface') {
            return [
                'type' => 'dotted',
                'enabled' => $this->booleanOrDefault($data['animated_background_enabled'] ?? null, true),
                'interactive' => $this->booleanOrDefault($data['animated_background_interactive'] ?? null, true),
                'speed' => $this->enum($data['animated_background_speed'] ?? null, ['slow', 'normal', 'fast'], 'slow'),
                'density' => $this->enum($data['animated_background_density'] ?? null, ['low', 'medium', 'high'], 'medium'),
                'opacity' => $this->clampedNumber($data['animated_background_opacity'] ?? null, 0.45, 0.1, 1),
                'background_color_override' => $this->colorOrNull($data['animated_background_color'] ?? null),
                'foreground_color_override' => $this->colorOrNull($data['animated_dots_color'] ?? null),
                'settings' => [],
            ];
        }

        if ($theme === 'animated_paths') {
            return [
                'type' => 'paths',
                'enabled' => $this->booleanOrDefault($data['paths_animation_enabled'] ?? null, true),
                'interactive' => false,
                'speed' => $this->enum($data['paths_speed'] ?? null, ['slow', 'normal', 'fast'], 'normal'),
                'density' => $this->enum($data['paths_density'] ?? null, ['low', 'medium', 'high'], 'medium'),
                'opacity' => $this->clampedNumber($data['paths_opacity'] ?? null, 0.35, 0.05, 1),
                'background_color_override' => $this->colorOrNull($data['paths_background_color'] ?? null),
                'foreground_color_override' => $this->colorOrNull($data['paths_color'] ?? null),
                'settings' => ['line_width' => $this->clampedNumber($data['paths_line_width'] ?? null, 1, 0.2, 3)],
            ];
        }

        return [
            'type' => 'none', 'enabled' => true, 'interactive' => true,
            'speed' => 'slow', 'density' => 'medium', 'opacity' => 0.45,
            'background_color_override' => null, 'foreground_color_override' => null,
            'settings' => [],
        ];
    }

    private function responsiveMedia(array $data, string $prefix): array
    {
        return [
            'width' => ['value' => $this->valueOrNull($data, "{$prefix}_width_value"), 'unit' => $this->valueOrNull($data, "{$prefix}_width_unit")],
            'height' => ['value' => $this->valueOrNull($data, "{$prefix}_height_value"), 'unit' => $this->valueOrNull($data, "{$prefix}_height_unit")],
            'fit' => $this->stringOrDefault($data["{$prefix}_fit"] ?? null, 'normal'),
        ];
    }

    private function emptyResponsiveMedia(): array
    {
        return ['width' => ['value' => null, 'unit' => null], 'height' => ['value' => null, 'unit' => null], 'fit' => 'normal'];
    }

    private function firstPresent(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    private function valueOrNull(array $data, string $key): mixed
    {
        return array_key_exists($key, $data) && $data[$key] !== '' ? $data[$key] : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function numericOrDefault(mixed $value, int|float $default): int|float
    {
        return is_numeric($value) ? $value + 0 : $default;
    }

    private function clampedNumber(mixed $value, int|float $default, int|float $minimum, int|float $maximum): int|float
    {
        return max($minimum, min($maximum, $this->numericOrDefault($value, $default)));
    }

    private function nonNegativeNumber(mixed $value): int|float|null
    {
        return is_numeric($value) ? max(0, $value + 0) : null;
    }

    private function colorOrNull(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
    }

    private function booleanOrDefault(mixed $value, bool $default): bool
    {
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
