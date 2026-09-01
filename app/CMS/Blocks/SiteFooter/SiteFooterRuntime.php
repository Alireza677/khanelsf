<?php

namespace App\CMS\Blocks\SiteFooter;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\Models\Media;
use App\Models\Menu;
use App\Services\MenuService;
use App\Services\SettingsService;

final class SiteFooterRuntime
{
    public function __construct(
        private readonly SiteFooterDataNormalizer $normalizer,
        private readonly RuntimeActionResolver $actions,
        private readonly ActionPresentation $presentation,
        private readonly SettingsService $settings,
        private readonly MenuService $menus,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function prepare(array $data, array $context = [], bool $preview = false): array
    {
        $footer = $this->normalizer->normalize($data);
        $content = $footer['content'];
        $menuColumns = [];

        foreach ($content['menu_columns'] as $column) {
            if (! $column['menu_id']) {
                continue;
            }

            $items = $this->navigation($this->menus->byId($column['menu_id']));

            if ($items === []) {
                continue;
            }

            $menuColumns[] = [
                'title' => $column['title'],
                'items' => $items,
            ];
        }

        $badgeMedia = Media::query()
            ->reusableImages()
            ->whereIn('id', collect($content['badges'])->pluck('media_id')->filter()->all())
            ->get()
            ->filter(fn (Media $media): bool => $media->isReusableImage())
            ->keyBy(fn (Media $media): int => (int) $media->getKey());
        $badges = collect($content['badges'])
            ->filter(fn (array $badge): bool => $badge['enabled'] && $badge['media_id'])
            ->map(function (array $badge) use ($badgeMedia): ?array {
                /** @var Media|null $media */
                $media = $badgeMedia->get($badge['media_id']);

                if (! $media) {
                    return null;
                }

                return [
                    'media_id' => (int) $media->getKey(),
                    'title' => $badge['title'] ?? $media->displayTitle(),
                    'url' => $badge['url'],
                    'image_url' => $media->getUrl(),
                    'alt' => $badge['alt'] ?: ($media->altText() ?: $media->displayTitle()),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $legalActions = [$content['privacy_action'], $content['terms_action']];
        $resolved = $this->actions->resolveMany(
            array_map(
                static fn (?array $action): ActionDestination => ActionDestination::fromArray($action ?? []),
                $legalActions,
            ),
            new ResolutionContext($preview ? ResolutionMode::Preview : ResolutionMode::Production),
        );
        $actionContext = [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? null,
            'block_id' => $footer['block_id'],
        ];

        return [
            'block_id' => $footer['block_id'],
            'site_name' => $this->settings->siteName(),
            'year' => now()->year,
            'menu_columns' => $menuColumns,
            'contact_title' => $content['contact_title'],
            'contacts' => array_values(array_filter([
                $this->contact('phone', 'تلفن', $this->settings->contactPhone(), 'tel:'),
                $this->contact('mobile', 'موبایل', $this->settings->contactMobile(), 'tel:'),
                $this->contact('email', 'ایمیل', $this->settings->contactEmail(), 'mailto:'),
                $this->contact('address', 'آدرس', $this->settings->contactAddress()),
                $this->contact('hours', 'ساعات کاری', $this->settings->workingHours()),
            ])),
            'about_title' => $content['about_title'],
            'about_text' => $content['about_text'] ?: $this->settings->footerText(),
            'badges_title' => $content['badges_title'],
            'badges' => $badges,
            'legal_links' => array_values(array_filter([
                $this->legalLink($content['privacy_label'], $resolved[0], $actionContext),
                $this->legalLink($content['terms_label'], $resolved[1], $actionContext),
            ])),
        ];
    }

    /** @return array<string, string>|null */
    private function contact(string $type, string $label, ?string $value, ?string $scheme = null): ?array
    {
        if (blank($value)) {
            return null;
        }

        $hrefValue = in_array($scheme, ['tel:', 'mailto:'], true)
            ? preg_replace('/\s+/u', '', (string) $value)
            : null;

        return [
            'type' => $type,
            'label' => $label,
            'value' => (string) $value,
            'href' => $scheme ? $scheme.$hrefValue : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function legalLink(string $label, mixed $resolved, array $context): ?array
    {
        $presentation = $this->presentation->present($resolved, $context);

        return $presentation ? ['label' => $label, 'presentation' => $presentation] : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function navigation(?Menu $menu): array
    {
        if (! $menu?->relationLoaded('rootItems')) {
            return [];
        }

        return $menu->rootItems
            ->map(function ($item): ?array {
                $url = $item->resolvedUrl();

                return filled($url) ? [
                    'label' => $item->title,
                    'url' => $url,
                    'target' => $item->target === '_blank' ? '_blank' : '_self',
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
