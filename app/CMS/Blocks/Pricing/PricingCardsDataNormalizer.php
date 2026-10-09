<?php

namespace App\CMS\Blocks\Pricing;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Support\HeadingLevel;
use App\Support\FormNumber;

final class PricingCardsDataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION = 1;

    public function normalize(array $data): array
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        return [
            'block_id' => $this->text($data['block_id'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
            'template' => 'default',
            'content' => [
                'title' => $this->text($content['title'] ?? null),
                'description' => $this->text($content['description'] ?? null),
                'cards' => $this->cards($content['cards'] ?? []),
            ],
            'settings' => [
                'heading_tag' => HeadingLevel::normalize($settings['heading_tag'] ?? null),
            ],
        ];
    }

    private function cards(mixed $cards): array
    {
        if (! is_array($cards)) {
            return [];
        }

        $normalized = [];

        foreach ($cards as $card) {
            if (! is_array($card) || ($title = $this->text($card['title'] ?? null)) === null) {
                continue;
            }

            $period = in_array($card['period'] ?? null, ['monthly', 'one_time', 'custom'], true)
                ? $card['period'] : 'monthly';
            $action = ActionDestination::fromArray(is_array($card['action'] ?? null) ? $card['action'] : []);

            $normalized[] = [
                'title' => $title,
                'description' => $this->text($card['description'] ?? null),
                'amount' => $this->amount($card['amount'] ?? null),
                'currency' => $this->text($card['currency'] ?? null) ?? 'تومان',
                'period' => $period,
                'period_label' => $period === 'custom' ? $this->text($card['period_label'] ?? null) : null,
                'show_price' => $this->boolean($card['show_price'] ?? null, true),
                'outputs' => $this->text($card['outputs'] ?? null),
                'features' => $this->features($card['features'] ?? []),
                'badge' => $this->text($card['badge'] ?? null),
                'featured' => $this->boolean($card['featured'] ?? null, false),
                'button_label' => $this->text($card['button_label'] ?? null),
                'action' => $action->type === null ? null : $action->toArray(),
            ];
        }

        return $normalized;
    }

    private function features(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $normalized = [];

        foreach ($features as $feature) {
            $text = is_array($feature) ? $this->text($feature['text'] ?? null) : null;

            if ($text !== null) {
                $normalized[] = ['text' => $text];
            }
        }

        return $normalized;
    }

    private function amount(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = (string) FormNumber::canonicalize($value);

        if (preg_match('/^\d+(?:\.\d{1,4})?$/', $value) !== 1) {
            return null;
        }

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');

        return $integer.($fraction !== '' ? '.'.$fraction : '');
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function boolean(mixed $value, bool $default): bool
    {
        return $value === null
            ? $default
            : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default);
    }
}
