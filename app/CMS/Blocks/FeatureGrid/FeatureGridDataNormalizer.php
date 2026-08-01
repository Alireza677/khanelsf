<?php

namespace App\CMS\Blocks\FeatureGrid;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Support\HeadingLevel;

final class FeatureGridDataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly FeatureGridLegacyActionAdapter $actions,
    ) {}

    public function normalize(array $data): array
    {
        $canonical = is_array($data['content'] ?? null)
            || is_array($data['settings'] ?? null);
        $content = $canonical ? ($data['content'] ?? []) : $data;
        $settings = $canonical ? ($data['settings'] ?? []) : $data;
        $mode = ($content['items_mode'] ?? null) === 'dynamic' ? 'dynamic' : 'static';

        return [
            'block_id' => $this->stringOrNull($data['block_id'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
            'template' => 'default',
            'content' => [
                'section_title' => $this->stringOrNull($content['section_title'] ?? null),
                'section_description' => $this->stringOrNull($content['section_description'] ?? null),
                'items_mode' => $mode,
                'dynamic_source' => ($content['dynamic_source'] ?? null) === 'projects'
                    ? 'projects'
                    : 'posts',
                'dynamic_button_label' => $this->stringOrNull(
                    $content['dynamic_button_label'] ?? null,
                ) ?? 'مشاهده بیشتر',
                'dynamic_button_overrides' => $this->overrides(
                    $content['dynamic_button_overrides'] ?? [],
                ),
                'items' => $this->items($content['items'] ?? []),
            ],
            'settings' => [
                'eyebrow' => $this->stringOrNull($settings['eyebrow'] ?? null),
                'heading_tag' => HeadingLevel::normalize($settings['heading_tag'] ?? null),
                'section_background' => in_array(
                    $settings['section_background'] ?? null,
                    ['muted', 'dark'],
                    true,
                ) ? $settings['section_background'] : 'default',
                'alignment' => ($settings['alignment'] ?? null) === 'left'
                    ? 'left'
                    : 'center',
                'dynamic_rows' => $this->boundedInteger(
                    $settings['dynamic_rows'] ?? 1,
                    1,
                    6,
                    1,
                ),
                'dynamic_columns' => $this->boundedInteger(
                    $settings['dynamic_columns'] ?? 3,
                    1,
                    12,
                    3,
                ),
                'dynamic_grid_width' => $this->boundedInteger(
                    $settings['dynamic_grid_width'] ?? 1180,
                    240,
                    2400,
                    1180,
                ),
                'dynamic_item_width' => $this->boundedInteger(
                    $settings['dynamic_item_width'] ?? 280,
                    120,
                    800,
                    280,
                ),
            ],
        ];
    }

    private function items(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $destination = $this->actions->adapt($item);
            $normalizedItem = [
                'title' => $this->stringOrNull($item['title'] ?? null),
                'description' => $this->stringOrNull($item['description'] ?? null),
                'icon' => $this->stringOrNull($item['icon'] ?? null),
                'icon_size' => $this->positiveNumberOrNull($item['icon_size'] ?? null),
                'image' => $this->stringOrNull($item['image'] ?? null),
                ...$this->imageSettings($item),
                'button_label' => $this->stringOrNull($item['button_label'] ?? null),
                'action' => $destination->type === null ? null : $destination->toArray(),
            ];

            if ($this->itemIsEmpty($normalizedItem)) {
                continue;
            }

            $normalized[] = $normalizedItem;
        }

        return $normalized;
    }

    private function imageSettings(array $item): array
    {
        $settings = [];

        foreach (['image', 'image_mobile'] as $prefix) {
            $settings["{$prefix}_width_value"] = $this->positiveNumberOrNull(
                $item["{$prefix}_width_value"] ?? null,
            );
            $settings["{$prefix}_width_unit"] = in_array(
                $item["{$prefix}_width_unit"] ?? null,
                ['%', 'px'],
                true,
            ) ? $item["{$prefix}_width_unit"] : null;
            $settings["{$prefix}_height_value"] = $this->positiveNumberOrNull(
                $item["{$prefix}_height_value"] ?? null,
            );
            $settings["{$prefix}_height_unit"] = in_array(
                $item["{$prefix}_height_unit"] ?? null,
                ['%', 'px'],
                true,
            ) ? $item["{$prefix}_height_unit"] : null;
            $settings["{$prefix}_fit"] = in_array(
                $item["{$prefix}_fit"] ?? null,
                ['normal', 'cover', 'contain'],
                true,
            ) ? $item["{$prefix}_fit"] : 'normal';
        }

        return $settings;
    }

    private function overrides(mixed $overrides): array
    {
        if (! is_array($overrides)) {
            return [];
        }

        $normalized = [];

        foreach ($overrides as $override) {
            if (! is_array($override)
                || ! is_numeric($override['record_id'] ?? null)
                || (int) $override['record_id'] <= 0
                || blank($override['button_label'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'record_id' => (int) $override['record_id'],
                'button_label' => $this->stringOrNull($override['button_label']),
            ];
        }

        return $normalized;
    }

    private function itemIsEmpty(array $item): bool
    {
        return blank($item['title'])
            && blank($item['description'])
            && blank($item['icon'])
            && blank($item['image'])
            && blank($item['button_label'])
            && $item['action'] === null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function positiveNumberOrNull(mixed $value): int|float|null
    {
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (float) $value === (float) (int) $value
            ? (int) $value
            : (float) $value;
    }

    private function boundedInteger(
        mixed $value,
        int $minimum,
        int $maximum,
        int $default,
    ): int {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($minimum, min((int) $value, $maximum));
    }
}
