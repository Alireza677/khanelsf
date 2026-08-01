<?php

namespace App\CMS\Blocks\SiteHeader;

use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use App\CMS\Blocks\Contracts\BlockNormalizer;

final class SiteHeaderDataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly ActionDestinationNormalizer $actions,
        private readonly ActionDestinationValidator $validator,
    ) {}

    public function normalize(array $data): array
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        return [
            'block_id' => $this->stringOrNull($data['block_id'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
            'template' => 'industrial-header-v1',
            'content' => [
                'top_actions' => [
                    $this->button(
                        $content['top_actions'][0] ?? null,
                        'خدمات و پشتیبانی',
                    ),
                    $this->button(
                        $content['top_actions'][1] ?? null,
                        'همکاری با ما',
                    ),
                ],
                'primary_action' => $this->button(
                    $content['primary_action'] ?? null,
                    'محاسبه هزینه ساخت',
                ),
            ],
            'settings' => [
                'menu_id' => $this->positiveIntegerOrNull($settings['menu_id'] ?? null),
                'search_enabled' => $this->boolean($settings['search_enabled'] ?? true),
                'sticky_enabled' => $this->boolean($settings['sticky_enabled'] ?? true),
                'top_bar_enabled' => $this->boolean($settings['top_bar_enabled'] ?? true),
            ],
        ];
    }

    private function button(mixed $button, string $defaultLabel): array
    {
        $button = is_array($button) ? $button : [];
        $input = is_array($button['action'] ?? null) ? $button['action'] : [];
        $destination = $this->actions->normalize($input);

        return [
            'label' => $this->stringOrNull($button['label'] ?? null) ?? $defaultLabel,
            'action' => $this->validator->validate($destination)->isValid()
                ? $destination->toArray()
                : null,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function positiveIntegerOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
