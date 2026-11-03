<?php

namespace App\CMS\Blocks\SiteFooter;

use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use App\Models\Media;
use App\Services\MenuService;
use App\Services\SettingsService;
use App\Support\RichText;
use Illuminate\Support\Facades\Storage;

/** Resolves the footer-owned composition into query-free view data. */
final class SiteFooterCompositionRuntime
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MenuService $menus,
        private readonly RuntimeActionResolver $actions,
        private readonly ActionPresentation $presentation,
        private readonly ActionDestinationNormalizer $destinations,
        private readonly ActionDestinationValidator $validator,
    ) {}

    public function prepare(array $footer, array $context = [], bool $preview = false): array
    {
        $composition = $footer['content']['composition'];
        $elements = $composition['bottom']['elements'];
        foreach ($composition['main']['columns'] as $column) {
            $elements = [...$elements, ...$column['elements']];
        }
        $mediaIds = [];
        foreach ($elements as $element) {
            if (! $element['enabled']) {
                continue;
            }
            $content = $element['content'];
            if ($element['type'] === 'branding' && $content['source'] !== 'site') {
                $mediaIds[] = $content['logo_media_id'];
            } elseif (in_array($element['type'], ['image', 'map'], true)) {
                $mediaIds[] = $content['media_id'];
            }
        }
        $mediaIds = array_values(array_unique(array_filter($mediaIds)));
        $media = $mediaIds === [] ? collect() : Media::query()->reusableImages()->whereIn('id', $mediaIds)->get()
            ->filter(fn (Media $media): bool => $media->isReusableImage())
            ->keyBy(fn (Media $media): int => (int) $media->getKey());
        // These caches live for one preparation only; Preview/requests/settings never share them.
        $resources = ['media' => $media, 'menus' => [], 'actions' => [], 'site' => [], 'published' => []];
        $context = [...$context, 'block_id' => $footer['block_id']];
        $columns = [];
        foreach ($composition['main']['columns'] as $column) {
            $prepared = [];
            foreach ($column['elements'] as $element) {
                $value = $this->element($element, $resources, $context, $preview);
                if ($value !== null) {
                    $prepared[] = $value;
                }
            }
            if ($prepared !== []) {
                $columns[] = [...$column, 'elements' => $prepared];
            }
        }
        $bottom = [];
        foreach ($composition['bottom']['elements'] as $element) {
            $value = $this->element($element, $resources, $context, $preview);
            if ($value !== null) {
                $bottom[] = $value;
            }
        }

        return [
            'block_id' => $footer['block_id'],
            'settings' => $footer['settings'],
            'composition' => ['main' => ['columns' => $columns], 'bottom' => ['elements' => $bottom]],
        ];
    }

    private function element(array $element, array &$resources, array $context, bool $preview): ?array
    {
        if (! $element['enabled'] || $element['type'] === 'form') {
            return null;
        }
        $content = $element['content'];
        $data = null;
        switch ($element['type']) {
            case 'branding':
                $source = $content['source'];
                $name = $this->inherit($content, 'name', $source === 'manual' ? null : $this->site('name', $resources));
                $logo = $source === 'site' || ($source === 'override' && $content['logo_media_id'] === null)
                    ? $this->site('logo', $resources)
                    : $this->image($content['logo_media_id'], $content['logo_alt'], $resources);
                if ($logo !== null) {
                    $logo['alt'] = $source !== 'site' && $content['logo_alt'] !== null
                        ? $content['logo_alt'] : ($logo['alt'] ?: ($name ?: 'لوگوی کسب‌وکار'));
                }
                if (filled($name) || $logo !== null) {
                    $data = ['name' => $name, 'logo' => $logo];
                }
                break;
            case 'rich_text':
                $text = $this->inherit($content, 'text', $content['source'] === 'manual' ? null : $this->site('text', $resources));
                $html = RichText::render($text);
                if (trim(html_entity_decode(strip_tags($html->toHtml()), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") !== '') {
                    $data = ['title' => $content['title'], 'html' => $html];
                }
                break;
            case 'navigation':
                $menuId = $content['menu_id'];
                if ($menuId !== null) {
                    if (! array_key_exists($menuId, $resources['menus'])) {
                        $resources['menus'][$menuId] = $this->menus->byId($menuId);
                    }
                    $menu = $resources['menus'][$menuId];
                    $items = $menu?->relationLoaded('rootItems') ? $this->navigation($menu->rootItems, $content['depth'], $resources) : [];
                    if ($items !== []) {
                        $data = ['title' => $content['title'], 'items' => $items];
                    }
                }
                break;
            case 'contact':
                $contacts = [];
                foreach ($content['fields'] as $field) {
                    $siteValue = $content['source'] === 'manual' ? null : $this->site($field, $resources);
                    $value = $content['source'] === 'site' ? $siteValue
                        : ($content['source'] === 'manual' ? ($content['overrides'][$field] ?? null) : ($content['overrides'][$field] ?? $siteValue));
                    if (blank($value)) {
                        continue;
                    }
                    $presentation = in_array($field, ['phone', 'mobile', 'email'], true)
                        ? $this->present(['type' => $field === 'email' ? 'email' : 'phone', 'value' => $value], $resources, $context, $preview) : null;
                    if (in_array($field, ['phone', 'mobile', 'email'], true) && $presentation === null) {
                        continue;
                    }
                    $contacts[] = [
                        'type' => $field,
                        'label' => ['phone' => 'تلفن', 'mobile' => 'موبایل', 'email' => 'ایمیل', 'address' => 'آدرس', 'working_hours' => 'ساعات کاری'][$field],
                        'value' => $value, 'presentation' => $presentation,
                    ];
                }
                if ($contacts !== []) {
                    $data = ['title' => $content['title'], 'contacts' => $contacts];
                }
                break;
            case 'social_links':
                $items = $content['items'];
                if ($content['source'] === 'site' || ($content['source'] === 'override' && $items === [])) {
                    $items = [];
                    foreach ($this->site('social', $resources) as $network => $link) {
                        $items[] = [
                            'label' => $link['label'], 'icon' => $network === 'instagram' ? 'icon-instagram' : 'heroicon-o-link',
                            'action' => ['type' => 'custom_url', 'value' => $link['url'], 'open_in_new_tab' => true],
                        ];
                    }
                }
                $links = $this->links($items, $resources, $context, $preview);
                if ($links !== []) {
                    $data = ['items' => $links];
                }
                break;
            case 'image':
            case 'map':
                $image = $this->image($content['media_id'], $content['alt'], $resources);
                $presentation = $this->present($content[$element['type'] === 'map' ? 'directions_action' : 'action'], $resources, $context, $preview);
                if ($image !== null || ($element['type'] === 'map' && $presentation !== null)) {
                    $data = ['title' => $content['title'], 'image' => $image, 'presentation' => $presentation];
                }
                break;
            case 'copyright':
                $text = $this->inherit($content, 'text', $content['source'] === 'manual' ? null : $this->site('name', $resources));
                if (filled($text)) {
                    $data = ['text' => $text, 'year' => $content['show_year'] ? now()->year : null];
                }
                break;
            case 'legal_links':
                $links = $this->links($content['items'], $resources, $context, $preview);
                if ($links !== []) {
                    $data = ['items' => $links];
                }
                break;
        }

        return $data === null ? null : ['id' => $element['id'], 'type' => $element['type'], ...$data];
    }

    private function inherit(array $content, string $key, mixed $site): mixed
    {
        return match ($content['source']) {
            'site' => $site,
            'manual' => $content[$key],
            'override' => $content[$key] ?? $site,
        };
    }

    private function site(string $key, array &$resources): mixed
    {
        if (! array_key_exists($key, $resources['site'])) {
            $resources['site'][$key] = match ($key) {
                'name' => trim($this->settings->siteName()),
                'logo' => $this->siteLogo(),
                'text' => $this->settings->footerText(),
                'phone' => $this->settings->contactPhone(),
                'mobile' => $this->settings->contactMobile(),
                'email' => $this->settings->contactEmail(),
                'address' => $this->settings->contactAddress(),
                'working_hours' => $this->settings->workingHours(),
                'social' => $this->settings->socialLinks(),
            };
        }

        return $resources['site'][$key];
    }

    private function siteLogo(): ?array
    {
        $raw = $this->settings->get('site_logo') ?: $this->settings->get('logo_path');
        $url = $this->settings->logoUrl();
        if (! is_string($raw) || ! $this->safeUrl($url)) {
            return null;
        }
        if (! preg_match('#^https?://#i', $raw)) {
            $path = ltrim($raw, '/');
            if (str_contains($path, '..') || (! Storage::disk('public')->exists(preg_replace('#^storage/#', '', $path)) && ! is_file(public_path($path)))) {
                return null;
            }
        }

        return ['url' => $url, 'alt' => trim($this->settings->siteName()), 'width' => null, 'height' => null];
    }

    private function image(?int $id, ?string $alt, array $resources): ?array
    {
        $media = $id === null ? null : $resources['media']->get($id);
        if (! $media instanceof Media || ! $this->safeUrl($media->getUrl())) {
            return null;
        }
        $dimensions = $media->imageDimensions();

        return [
            'url' => $media->getUrl(), 'alt' => $alt ?: ($media->altText() ?: $media->displayTitle()),
            'width' => $dimensions['width'] ?? null, 'height' => $dimensions['height'] ?? null,
        ];
    }

    private function present(?array $action, array &$resources, array $context, bool $preview): ?array
    {
        if ($action === null || ($action['type'] ?? null) === 'form') {
            return null;
        }
        $destination = $this->destinations->normalize($action);
        if (! $this->validator->validate($destination)->isValid()) {
            return null;
        }
        $key = json_encode($destination->toArray(), JSON_THROW_ON_ERROR);
        if (! array_key_exists($key, $resources['actions'])) {
            $resolved = $this->actions->resolve($destination, new ResolutionContext($preview ? ResolutionMode::Preview : ResolutionMode::Production));
            $presentation = $this->presentation->present($resolved, $context);
            $resources['actions'][$key] = ($presentation['kind'] ?? null) === 'link' && ! $presentation['prevent_default'] ? $presentation : null;
        }

        return $resources['actions'][$key];
    }

    private function links(array $items, array &$resources, array $context, bool $preview): array
    {
        $links = [];
        foreach ($items as $item) {
            $presentation = $this->present($item['action'], $resources, $context, $preview);
            if (filled($item['label']) && $presentation !== null) {
                $icon = $item['icon'] ?? null;
                $links[] = [
                    'label' => $item['label'], 'presentation' => $presentation,
                    'icon' => is_string($icon) && preg_match('/^(?:icon-|heroicon-)[A-Za-z0-9_-]+$/D', $icon) ? $icon : null,
                ];
            }
        }

        return $links;
    }

    private function navigation(iterable $items, int $depth, array &$resources): array
    {
        $links = [];
        foreach ($items as $item) {
            $url = $item->resolvedUrl();
            if (blank($item->title) || ! $this->safeUrl($url)) {
                continue;
            }
            // Core resolves reference URLs; reuse the referenced model's public scope as well.
            // A draft may have a resolvable slug but still have no public destination.
            $reference = $item->relationLoaded('reference') ? $item->getRelation('reference') : null;
            if ($reference !== null && method_exists($reference, 'scopePublished')) {
                $key = $reference::class.':'.$reference->getKey();
                $resources['published'][$key] ??= $reference->newQuery()->published()->whereKey($reference->getKey())->exists();
                if (! $resources['published'][$key]) {
                    continue;
                }
            }
            $links[] = [
                'label' => $item->title, 'url' => $url, 'target' => $item->target === '_blank' ? '_blank' : '_self',
                'children' => $depth > 1 && $item->relationLoaded('children') ? $this->navigation($item->children, $depth - 1, $resources) : [],
            ];
        }

        return $links;
    }

    private function safeUrl(?string $url): bool
    {
        return filled($url) && $url !== '#' && $this->validator->validate($this->destinations->normalize(['type' => 'custom_url', 'value' => $url]))->isValid();
    }
}
