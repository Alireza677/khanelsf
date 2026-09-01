<?php

namespace App\CMS\Blocks\SiteFooter;

use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use App\CMS\Blocks\Contracts\BlockNormalizer;

final class SiteFooterDataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly ActionDestinationNormalizer $actions,
        private readonly ActionDestinationValidator $validator,
    ) {}

    public function normalize(array $data): array
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];

        return [
            'block_id' => $this->stringOrNull($data['block_id'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
            'template' => 'corporate-dark-v1',
            'content' => [
                'menu_columns' => collect($content['menu_columns'] ?? [])
                    ->filter(fn (mixed $column): bool => is_array($column))
                    ->take(3)
                    ->map(fn (array $column): array => [
                        'title' => $this->stringOrNull($column['title'] ?? null),
                        'menu_id' => $this->positiveIntegerOrNull($column['menu_id'] ?? null),
                    ])
                    ->values()
                    ->all(),
                'contact_title' => $this->stringOrNull($content['contact_title'] ?? null) ?? 'ارتباط با ما',
                'about_title' => $this->stringOrNull($content['about_title'] ?? null) ?? 'درباره ما',
                'about_text' => $this->stringOrNull($content['about_text'] ?? null),
                'badges_title' => $this->stringOrNull($content['badges_title'] ?? null) ?? 'مجوزها و نمادهای اعتماد',
                'badges' => collect($content['badges'] ?? [])
                    ->filter(fn (mixed $badge): bool => is_array($badge))
                    ->map(fn (array $badge, int $index): array => [
                        'title' => $this->stringOrNull($badge['title'] ?? null),
                        'media_id' => $this->positiveIntegerOrNull($badge['media_id'] ?? null),
                        'url' => $this->safeUrlOrNull($badge['url'] ?? null),
                        'alt' => $this->stringOrNull($badge['alt'] ?? null),
                        'sort_order' => is_numeric($badge['sort_order'] ?? null) ? (int) $badge['sort_order'] : $index,
                        'enabled' => $this->boolean($badge['enabled'] ?? true),
                    ])
                    ->sortBy('sort_order')
                    ->values()
                    ->all(),
                'privacy_label' => $this->stringOrNull($content['privacy_label'] ?? null) ?? 'حریم خصوصی',
                'privacy_action' => $this->validAction($content['privacy_action'] ?? null),
                'terms_label' => $this->stringOrNull($content['terms_label'] ?? null) ?? 'قوانین و مقررات',
                'terms_action' => $this->validAction($content['terms_action'] ?? null),
            ],
        ];
    }

    private function validAction(mixed $action): ?array
    {
        $destination = $this->actions->normalize(is_array($action) ? $action : []);

        return $this->validator->validate($destination)->isValid()
            ? $destination->toArray()
            : null;
    }

    private function safeUrlOrNull(mixed $value): ?string
    {
        $url = $this->stringOrNull($value);

        if ($url === null || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url
            : null;
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
