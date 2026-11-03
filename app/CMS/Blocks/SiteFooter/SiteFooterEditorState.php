<?php

namespace App\CMS\Blocks\SiteFooter;

use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Filament\ActionPickerService;
use App\CMS\Actions\Filament\Exceptions\InvalidActionPickerState;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use App\Models\Media;
use App\Services\SettingsService;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Explicit editor operations only. Never called by the normalizer or public renderer. */
final class SiteFooterEditorState
{
    public const ACTION_TYPES = ['custom_url', 'page', 'project', 'product', 'service', 'anchor', 'phone', 'email'];

    public const MAIN_LABELS = [
        'branding' => 'هویت کسب‌وکار', 'navigation' => 'منوی دسترسی', 'contact' => 'اطلاعات تماس',
        'social_links' => 'شبکه‌های اجتماعی', 'rich_text' => 'عنوان و متن', 'image' => 'تصویر / نشان اعتماد', 'map' => 'نقشه ایستا',
    ];

    public const BOTTOM_LABELS = ['copyright' => 'حق نشر', 'legal_links' => 'لینک‌های حقوقی'];

    public const CONTACT_LABELS = ['phone' => 'تلفن', 'mobile' => 'موبایل', 'email' => 'ایمیل', 'address' => 'آدرس', 'working_hours' => 'ساعات کاری'];

    public const LEGACY_FIELDS = ['menu_columns', 'contact_title', 'about_title', 'about_text', 'badges_title', 'badges', 'privacy_label', 'privacy_action', 'terms_label', 'terms_action'];

    public static function emptyComposition(): array
    {
        return ['main' => ['columns' => []], 'bottom' => ['elements' => []]];
    }

    public static function column(?string $title = null, array $elements = []): array
    {
        return ['id' => (string) Str::ulid(), 'title' => $title, 'settings' => ['span' => 1], 'elements' => $elements];
    }

    public static function element(string $type, array $content = [], bool $enabled = true): array
    {
        $element = ['id' => (string) Str::ulid(), 'type' => $type, 'enabled' => $enabled, 'content' => $content];
        $composition = self::emptyComposition();
        $bottom = array_key_exists($type, self::BOTTOM_LABELS);
        if ($bottom) {
            $composition['bottom']['elements'][] = $element;
        } else {
            $composition['main']['columns'][] = self::column(elements: [$element]);
        }
        $normalized = app(SiteFooterCompositionNormalizer::class)->normalize($composition);

        return $bottom ? $normalized['bottom']['elements'][0] : $normalized['main']['columns'][0]['elements'][0];
    }

    public static function copyColumn(array $column): array
    {
        $column['id'] = (string) Str::ulid();
        foreach ($column['elements'] ?? [] as $key => $element) {
            $column['elements'][$key]['id'] = (string) Str::ulid();
        }

        return $column;
    }

    /** The original legacy fields stay in content; conversion changes form state only. */
    public static function convert(array $legacy): array
    {
        $columns = [];
        foreach ($legacy['menu_columns'] ?? [] as $menu) {
            $columns[] = self::column($menu['title'] ?? null, [self::element('navigation', [
                'menu_id' => $menu['menu_id'] ?? null,
            ])]);
        }
        $columns[] = self::column($legacy['contact_title'] ?? null, [self::element('contact')]);
        $columns[] = self::column($legacy['about_title'] ?? null, [self::element('rich_text', [
            'source' => filled($legacy['about_text'] ?? null) ? 'manual' : 'site',
            'text' => $legacy['about_text'] ?? null,
        ])]);
        $badges = collect($legacy['badges'] ?? [])->sortBy('sort_order')->values()->all();
        foreach (array_chunk($badges, SiteFooterCompositionNormalizer::MAX_ELEMENTS_PER_COLUMN) as $chunk) {
            $elements = [];
            foreach ($chunk as $badge) {
                $action = null;
                if (filled($badge['url'] ?? null)) {
                    $destination = app(ActionDestinationNormalizer::class)->normalize(['type' => 'custom_url', 'value' => $badge['url']]);
                    if (! app(ActionDestinationValidator::class)->validate($destination)->isValid()) {
                        throw ValidationException::withMessages(['content.composition' => 'لینک یکی از نشان‌های قدیمی معتبر نیست؛ ابتدا آن را اصلاح کنید. تبدیل انجام نشد.']);
                    }
                    $action = $destination->toArray();
                }
                $elements[] = self::element('image', [
                    'title' => $badge['title'] ?? null, 'media_id' => $badge['media_id'] ?? null,
                    'alt' => $badge['alt'] ?? null, 'action' => $action,
                ], (bool) ($badge['enabled'] ?? true));
            }
            $columns[] = self::column($legacy['badges_title'] ?? null, $elements);
        }
        $links = [];
        foreach (['privacy', 'terms'] as $key) {
            $action = app(ActionPickerService::class)->dehydrate($legacy[$key.'_action'] ?? null, false, self::ACTION_TYPES);
            $links[] = ['label' => $legacy[$key.'_label'] ?? null, 'action' => $action];
        }

        return app(SiteFooterCompositionNormalizer::class)->normalize([
            'main' => ['columns' => $columns],
            'bottom' => ['elements' => [self::element('copyright'), self::element('legal_links', ['items' => $links])]],
        ]);
    }

    public static function label(array $state): string
    {
        $label = (self::MAIN_LABELS + self::BOTTOM_LABELS + ['form' => 'فرم (خارج از این فاز)'])[$state['type'] ?? ''] ?? 'عنصر';
        $title = $state['content']['title'] ?? $state['content']['name'] ?? null;

        return $label.(filled($title) ? ' · '.$title : '').(($state['enabled'] ?? true) ? '' : ' · غیرفعال');
    }

    public static function inherited(string $type): HtmlString
    {
        $settings = app(SettingsService::class);
        $values = match ($type) {
            'branding' => ['نام سایت' => $settings->siteName(), 'لوگوی سایت' => $settings->logoUrl()],
            'rich_text' => ['معرفی سایت' => strip_tags((string) $settings->footerText())],
            'copyright' => ['نام سایت' => $settings->siteName(), 'سال جاری' => (string) now()->year],
            'contact' => ['تلفن' => $settings->contactPhone(), 'موبایل' => $settings->contactMobile(), 'ایمیل' => $settings->contactEmail(), 'آدرس' => $settings->contactAddress(), 'ساعات کاری' => $settings->workingHours()],
            'social_links' => collect($settings->socialLinks())->mapWithKeys(fn (array $link): array => [$link['label'] => $link['url']])->all(),
            default => [],
        };
        $html = '<dl dir="rtl">';
        foreach ($values as $label => $value) {
            $html .= '<div><dt><strong>'.e($label).'</strong></dt><dd><bdi>'.e(filled($value) ? $value : 'در تنظیمات سایت تعریف نشده').'</bdi></dd></div>';
        }

        return new HtmlString($html.($values === [] ? '<div><dt>محتوا</dt><dd>در تنظیمات سایت تعریف نشده</dd></div>' : '').'</dl>');
    }

    public static function mediaWarning(mixed $id): string
    {
        if (blank($id)) {
            return 'تصویری انتخاب نشده؛ در خروجی عمومی تصویر خالی نمایش داده نمی‌شود.';
        }

        return Media::reusableImage($id) ? 'تصویر از کتابخانه رسانه خوانده می‌شود.' : 'هشدار: رسانه انتخاب‌شده حذف شده، فایل آن موجود نیست یا تصویر قابل استفاده نیست. شناسه تا انتخاب شما حفظ می‌شود.';
    }

    /** Read-only diagnostic through the same normalizer/runtime, never a second renderer. */
    public static function diagnostic(array $element): string
    {
        if (! ($element['enabled'] ?? true)) {
            return 'غیرفعال است؛ تنظیمات حفظ می‌شوند و در سایت نمایش داده نمی‌شود.';
        }
        if (($element['type'] ?? null) === 'form') {
            return 'هشدار: داده فرم حفظ شده است؛ ویرایش و نمایش فرم خارج از این فاز است.';
        }
        try {
            $element = array_intersect_key($element, array_flip(['id', 'type', 'enabled', 'content']));
            $content = $element['content'] ?? [];
            foreach (['action', 'directions_action'] as $key) {
                if (array_key_exists($key, $content)) {
                    $content[$key] = app(ActionPickerService::class)->dehydrate($content[$key], false, [...self::ACTION_TYPES, 'form']);
                }
            }
            foreach ($content['items'] ?? [] as $key => $item) {
                $content['items'][$key] = array_intersect_key($item, array_flip(['label', 'icon', 'action']));
                $content['items'][$key]['action'] = app(ActionPickerService::class)->dehydrate($item['action'] ?? null, false, [...self::ACTION_TYPES, 'form']);
            }
            $element['content'] = $content;
            $composition = self::emptyComposition();
            $bottom = array_key_exists($element['type'], self::BOTTOM_LABELS);
            if ($bottom) {
                $composition['bottom']['elements'] = [$element];
            } else {
                // Fixed diagnostic identity; never written to the document.
                $columnId = ($element['id'] ?? null) === '00000000000000000000000000' ? '00000000000000000000000001' : '00000000000000000000000000';
                $composition['main']['columns'] = [['id' => $columnId, 'elements' => [$element]]];
            }
            $data = app(SiteFooterDataNormalizer::class)->normalize([
                'schema_version' => 2, 'content' => ['composition' => $composition],
            ]);
            $runtime = app(SiteFooterCompositionRuntime::class)->prepare($data);
            $rendered = $bottom ? $runtime['composition']['bottom']['elements'] : ($runtime['composition']['main']['columns'][0]['elements'] ?? []);

            return $rendered !== []
                ? 'محتوای قابل نمایش دارد. فیلدها و لینک‌های ناقص همچنان در خروجی عمومی پنهان می‌شوند؛ پیش‌نمایش پس از ذخیره را بررسی کنید.'
                : 'هشدار: اطلاعات مؤثر یا مرجع قابل نمایش ندارد؛ در سایت پنهان می‌شود، اما تنظیمات این عنصر حفظ خواهند شد.';
        } catch (ValidationException|InvalidActionPickerState $exception) {
            return 'هشدار: تنظیمات این عنصر ناقص یا نامعتبر است؛ فیلدها و مقصدها را کامل کنید. ذخیره داده نامعتبر مجاز نیست.';
        }
    }

    public static function actionWarning(mixed $action): string
    {
        if (! is_array($action) || blank($action['type'] ?? null)) {
            return 'مقصدی انتخاب نشده؛ لینک خالی ساخته نمی‌شود. تصویر معتبر می‌تواند بدون لینک نمایش داده شود.';
        }
        if (($action['type'] ?? null) === 'form') {
            return 'هشدار: مقصد فرم در این فاز نمایش داده نمی‌شود.';
        }
        $service = app(ActionPickerService::class);
        if ($message = $service->validationMessage($action, false, self::ACTION_TYPES)) {
            return 'هشدار: '.$message;
        }
        $destination = app(ActionDestinationNormalizer::class)->normalize($action);
        $resolved = app(RuntimeActionResolver::class)->resolve($destination, new ResolutionContext);
        $presentation = app(ActionPresentation::class)->present($resolved);

        return ($presentation['kind'] ?? null) === 'link' && ! $presentation['prevent_default']
            ? 'مقصد برای نمایش عمومی قابل استفاده است.'
            : 'هشدار: مقصد حذف‌شده، منتشرنشده یا غیرقابل نمایش است؛ لینک آن در سایت پنهان می‌شود.';
    }
}
