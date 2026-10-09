<?php

namespace App\CMS\Blocks\Pricing;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\Support\FormNumber;
use App\Support\PersianDate;

final class PricingCardsRuntime
{
    public function __construct(
        private readonly PricingCardsDataNormalizer $normalizer,
        private readonly RuntimeActionResolver $actions,
        private readonly ActionPresentation $presentation,
    ) {}

    public function prepare(array $data, array $context = [], bool $preview = false): array
    {
        $pricing = $this->normalizer->normalize($data);
        $cards = $pricing['content']['cards'];
        $destinations = array_map(
            fn (array $card): ActionDestination => ActionDestination::fromArray($card['action'] ?? []),
            $cards,
        );
        $resolved = $this->actions->resolveMany(
            $destinations,
            new ResolutionContext($preview ? ResolutionMode::Preview : ResolutionMode::Production),
        );
        $actionContext = [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? request()->getRequestUri(),
            'block_id' => $pricing['block_id'],
        ];

        foreach ($cards as $index => $card) {
            $cards[$index]['formatted_amount'] = $card['show_price'] && $card['amount'] !== null
                ? PersianDate::digits(FormNumber::format($card['amount'], true)) : null;
            $cards[$index]['period_text'] = match ($card['period']) {
                'one_time' => 'یک‌بار',
                'custom' => $card['period_label'],
                default => 'ماهانه',
            };
            $cards[$index]['presentation'] = $this->presentation->present($resolved[$index], $actionContext);
        }

        return [...$pricing, 'cards' => $cards];
    }
}
