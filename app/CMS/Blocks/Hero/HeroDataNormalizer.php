<?php

namespace App\CMS\Blocks\Hero;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Support\HeadingLevel;

final class HeroDataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION = 2;

    public function __construct(private readonly HeroMediaResolver $mediaResolver) {}

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

        return [
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
                'primary_cta' => [
                    'label' => $this->valueOrNull($data, 'primary_button_label'),
                    'url' => $this->valueOrNull($data, 'primary_button_url'),
                ],
                'secondary_cta' => [
                    'label' => $this->valueOrNull($data, 'secondary_button_label'),
                    'url' => $this->valueOrNull($data, 'secondary_button_url'),
                ],
                'selector' => $this->selector($data),
                'stats' => $this->arrayOrEmpty($data['stats'] ?? null),
                'social_links' => $this->arrayOrEmpty($data['hero_1_social_links'] ?? null),
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
    }

    private function normalizeV2(array $data): array
    {
        $normalized = array_replace_recursive($this->emptyContract(), $data);
        $normalized['schema_version'] = self::SCHEMA_VERSION;
        $normalized['block_id'] = $this->stringOrNull($normalized['block_id']);
        $normalized['settings']['heading_tag'] = HeadingLevel::normalize(
            $normalized['settings']['heading_tag'] ?? null,
        );

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
                'primary_cta' => ['label' => null, 'url' => null],
                'secondary_cta' => ['label' => null, 'url' => null],
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

    private function selector(array $data): ?array
    {
        $items = $this->arrayOrEmpty($data['selector_items'] ?? null);
        $placeholder = $this->valueOrNull($data, 'selector_placeholder');

        return $items === [] && $placeholder === null ? null : ['placeholder' => $placeholder, 'items' => $items];
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
