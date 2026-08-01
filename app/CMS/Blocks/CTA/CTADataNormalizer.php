<?php

namespace App\CMS\Blocks\CTA;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Support\HeadingLevel;

final class CTADataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION = 2;

    public function __construct(
        private readonly CTALegacyActionAdapter $actions,
    ) {}

    public function normalize(array $data): array
    {
        $v2 = (int) ($data['schema_version'] ?? 1) >= self::SCHEMA_VERSION
            || is_array($data['content'] ?? null);

        return [
            'block_id' => $this->stringOrNull($data['block_id'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
            'template' => $this->template($v2 ? ($data['template'] ?? null) : ($data['cta_template'] ?? null)),
            'content' => [
                'eyebrow' => $this->value($data, $v2 ? 'content.eyebrow' : 'eyebrow'),
                'title' => $this->value($data, $v2 ? 'content.title' : 'title'),
                'description' => $this->value($data, $v2 ? 'content.description' : 'description'),
                'primary_cta' => [
                    'label' => $this->value($data, $v2 ? 'content.primary_cta.label' : 'button_label'),
                    'action' => $this->action($data, $v2, 'primary_cta', 'button_url'),
                ],
                'secondary_cta' => [
                    'label' => $this->value($data, $v2 ? 'content.secondary_cta.label' : 'secondary_button_label'),
                    'action' => $this->action($data, $v2, 'secondary_cta', 'secondary_button_url'),
                ],
                'media' => [
                    'url' => $this->value($data, $v2 ? 'content.media.url' : 'background_image'),
                ],
            ],
            'settings' => [
                'heading_tag' => $this->headingTag($this->value($data, $v2 ? 'settings.heading_tag' : 'heading_tag')),
                'alignment' => $this->alignment($this->value($data, $v2 ? 'settings.alignment' : 'alignment')),
                'background' => $this->background($this->value($data, $v2 ? 'settings.background' : 'section_background')),
                'content_width' => $this->numberOrNull($this->value($data, $v2 ? 'settings.content_width' : 'content_width')),
                'media' => $this->mediaSettings($data, $v2),
            ],
        ];
    }

    private function mediaSettings(array $data, bool $v2): array
    {
        return [
            'desktop' => $this->deviceSettings($data, $v2 ? 'settings.media.desktop' : 'background_image'),
            'mobile' => $this->deviceSettings($data, $v2 ? 'settings.media.mobile' : 'background_image_mobile'),
        ];
    }

    private function action(array $data, bool $v2, string $cta, string $legacyUrl): ?array
    {
        if (! $v2) {
            $legacy = [
                'type' => 'url',
                'url' => $this->stringOrNull($data[$legacyUrl] ?? null),
            ];
        } else {
            $action = $this->value($data, "content.{$cta}.action");
            $legacy = is_array($action) ? $action : [];

            if (! array_key_exists('url', $legacy) && ! array_key_exists('value', $legacy)) {
                $legacy['url'] = $this->value($data, "content.{$cta}.url");
            }
        }

        $destination = $this->actions->adapt($legacy);

        return $destination->type === null ? null : $destination->toArray();
    }

    private function deviceSettings(array $data, string $path): array
    {
        $nested = str_starts_with($path, 'settings.');

        return [
            'width' => [
                'value' => $this->numberOrNull($this->value($data, $nested ? "{$path}.width.value" : "{$path}_width_value")),
                'unit' => $this->unit($this->value($data, $nested ? "{$path}.width.unit" : "{$path}_width_unit")),
            ],
            'height' => [
                'value' => $this->numberOrNull($this->value($data, $nested ? "{$path}.height.value" : "{$path}_height_value")),
                'unit' => $this->unit($this->value($data, $nested ? "{$path}.height.unit" : "{$path}_height_unit")),
            ],
            'fit' => $this->fit($this->value($data, $nested ? "{$path}.fit" : "{$path}_fit")),
        ];
    }

    private function value(array $data, string $path): mixed
    {
        return data_get($data, $path);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function numberOrNull(mixed $value): int|float|null
    {
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (float) $value === (float) (int) $value ? (int) $value : (float) $value;
    }

    private function template(mixed $value): string
    {
        return in_array($value, ['classic', 'image'], true) ? $value : 'classic';
    }

    private function headingTag(mixed $value): string
    {
        return HeadingLevel::normalize($value);
    }

    private function alignment(mixed $value): string
    {
        return $value === 'center' ? 'center' : 'left';
    }

    private function background(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'dark';
        }

        return in_array($value, ['default', 'muted', 'dark'], true) ? $value : 'default';
    }

    private function unit(mixed $value): ?string
    {
        return in_array($value, ['px', '%'], true) ? $value : null;
    }

    private function fit(mixed $value): string
    {
        return in_array($value, ['normal', 'cover', 'contain'], true) ? $value : 'normal';
    }
}
