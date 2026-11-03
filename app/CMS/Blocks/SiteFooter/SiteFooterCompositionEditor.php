<?php

namespace App\CMS\Blocks\SiteFooter;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Actions\Filament\ActionPickerService;
use App\CMS\Actions\Filament\Exceptions\InvalidActionPickerState;
use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Models\Menu;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Filament schema for the existing site_footer block, not a builder or registry. */
final class SiteFooterCompositionEditor
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;

    public static function schema(array $legacy, int $version, string $template): array
    {
        return [
            Hidden::make('block_id'),
            Hidden::make('schema_version')->default($version),
            Hidden::make('template')->default($template),
            ViewField::make('content.footer_editor_status')->label('راهنمای ذخیره و پیش‌نمایش')
                ->view('filament.forms.components.site-footer-editor-status')
                ->viewData(fn (ViewField $component): array => [
                    'footerStatePath' => $component->getContainer()->getStatePath(),
                    'footerErrors' => collect($component->getLivewire()->getErrorBag()->getMessages())
                        ->filter(fn ($messages, string $key): bool => str_starts_with($key, $component->getContainer()->getStatePath().'.') || str_starts_with($key, 'content.composition') || str_starts_with($key, 'settings.'))->all(),
                ])->dehydrated(false)->columnSpanFull(),
            Placeholder::make('content.footer_editor_help')->label('ویرایش فوتر')
                ->content(fn (Get $get): string => $get('content.composition') === null
                    ? 'چیدمان قدیمی فعال است و بدون تبدیل قابل ویرایش می‌ماند. تبدیل فقط با انتخاب شما انجام می‌شود.'
                    : 'ستون‌ها را باز کنید و عناصر را مدیریت کنید. ترتیب فهرست، ترتیب نمایش است. دکمه‌های بالا و پایین جایگزین کشیدن هستند؛ انتقال به ستون دیگر از منوی هر عنصر انجام می‌شود.')
                ->columnSpanFull(),
            // Build actions after the Builder has cloned/bound this block's container.
            Group::make()->schema(fn (): array => [Actions::make([
                Action::make('convertFooter')->label('تبدیل اختیاری به Composition')->icon('heroicon-o-squares-plus')
                    ->visible(fn (Get $get): bool => $get('content.composition') === null)
                    ->modalHeading('تبدیل چیدمان قدیمی')
                    ->modalDescription('این اقدام فقط فرم را تغییر می‌دهد؛ برای اعمال در سایت باید ذخیره کنید. ظاهر چیدمان جدید ممکن است متفاوت باشد. داده قدیمی برای بازگشت حفظ می‌شود. پیش‌نمایش تنها نسخه ذخیره‌شده را نشان می‌دهد.')
                    ->form([Select::make('mode')->label('شروع چیدمان')->options([
                        'legacy' => 'انتقال محتوای فعلی به ستون‌ها', 'empty' => 'شروع با چیدمان خالی',
                    ])->default('legacy')->required()->native(false)])
                    ->modalSubmitActionLabel('تبدیل در فرم')
                    ->action(function (array $data, Get $get, Set $set, Component $component): void {
                        try {
                            $composition = $data['mode'] === 'empty'
                                ? SiteFooterEditorState::emptyComposition()
                                : SiteFooterEditorState::convert($get('content') ?? []);
                        } catch (ValidationException|InvalidActionPickerState $exception) {
                            Notification::make()->title('تبدیل انجام نشد')->body('ابتدا لینک‌های قدیمی را اصلاح کنید و محدودیت ۸ ستون، ۱۲ عنصر در ستون و ۴۸ عنصر کل را رعایت کنید. داده قبلی تغییر نکرد.')->danger()->send();

                            return;
                        }
                        $set('content.composition', $composition);
                        $set('schema_version', 2);
                        // Hydrate newly mounted repeaters and shared pickers, keeping the raw legacy snapshot.
                        self::refillBlock($component, $get(''));
                        Notification::make()->title('چیدمان در فرم تبدیل شد؛ برای اعمال، ذخیره کنید.')->success()->send();
                    }),
                Action::make('returnToLegacy')->label('بازگشت به محتوای قدیمی')->color('gray')
                    ->visible(fn (Get $get): bool => $get('content.composition') !== null)
                    ->requiresConfirmation()->modalHeading('بازگشت به چیدمان قدیمی؟')
                    ->modalDescription('Composition فعلی از فرم کنار گذاشته می‌شود و محتوای قدیمی حفظ‌شده باز می‌گردد. تغییر سایت تنها پس از ذخیره است. برای لغو تغییر ذخیره‌نشده، صفحه را بازخوانی کنید.')
                    ->action(function (Get $get, Set $set, Component $component): void {
                        $set('content.composition', null);
                        self::refillBlock($component, $get(''));
                    }),
            ])])->columnSpanFull(),
            // Only mount the active schema. Hidden duplicate state paths would overwrite nested dehydration.
            Group::make()->schema(fn (Get $get): array => $get('content.composition') === null
                ? [Hidden::make('content.composition'), ...$legacy]
                : [...array_map(fn (string $key): Hidden => Hidden::make('content.'.$key), SiteFooterEditorState::LEGACY_FIELDS), ...self::compositionFields()])
                ->columnSpanFull(),
            Section::make('تنظیمات طراحی')->description('عرض، فاصله‌ها و شبکه واکنش‌گرا در سایت و پیش‌نمایش ذخیره‌شده اعمال می‌شوند. حالت خودکار با عرض واقعی ظرف سازگار می‌شود.')
                ->schema([
                    Select::make('settings.container')->label('عرض محتوا')->options(['default' => 'استاندارد', 'narrow' => 'باریک', 'full' => 'تمام عرض'])->default('default')->required()->native(false),
                    Select::make('settings.spacing')->label('فاصله‌گذاری')->options(['compact' => 'فشرده', 'comfortable' => 'متعادل', 'spacious' => 'باز'])->default('comfortable')->required()->native(false),
                    Select::make('settings.grid.desktop')->label('تعداد ستون دسکتاپ')->options(['auto' => 'خودکار'] + self::numbers(8))->default('auto')->required()->native(false),
                    Select::make('settings.grid.tablet')->label('تعداد ستون تبلت')->options(self::numbers(4))->default(2)->required()->native(false),
                    Select::make('settings.grid.mobile')->label('تعداد ستون موبایل')->options(self::numbers(2))->default(1)->required()->native(false),
                ])->columns(2)->visible(fn (Get $get): bool => $get('content.composition') !== null)->dehydratedWhenHidden()->columnSpanFull(),
            Placeholder::make('context_note')->label('اطلاعات پیشرفته')
                ->content(fn (Get $get): string => 'شناسه بلوک: '.($get('block_id') ?: 'هنگام ذخیره تعیین می‌شود').' | Schema v2. شناسه ستون و عنصر فقط هنگام افزودن یا کپی ساخته می‌شود؛ جابه‌جایی آن را تغییر نمی‌دهد. CSS یا کد دلخواه در این قرارداد وجود ندارد.')
                ->columnSpanFull(),
        ];
    }

    private static function numbers(int $max): array
    {
        return array_combine(range(1, $max), range(1, $max));
    }

    private static function compositionFields(): array
    {
        return [
            Repeater::make('content.composition.main.columns')->label('ستون‌های بخش اصلی')
                ->schema([
                    Hidden::make('id'),
                    TextInput::make('title')->label('عنوان ستون')->maxLength(255)->live(onBlur: true)->helperText('اختیاری؛ بالای عناصر ستون در سایت نمایش داده می‌شود.'),
                    Select::make('settings.span')->label('سهم عرض ستون')->options(self::numbers(8))->required()->native(false)
                        ->helperText('از ۱ تا ۸ خانه شبکه؛ در هر اندازه صفحه به تعداد ستون‌های همان شبکه محدود می‌شود.'),
                    self::elements('elements', false),
                    Placeholder::make('column_identity')->label('شناسه ثابت ستون')->content(fn (Get $get): string => (string) $get('id')),
                ])->defaultItems(0)->maxItems(SiteFooterCompositionNormalizer::MAX_COLUMNS)
                ->addActionLabel('افزودن ستون')->addAction(fn (Action $action): Action => $action->action(function (Repeater $component): void {
                    if (count($component->getState() ?? []) < SiteFooterCompositionNormalizer::MAX_COLUMNS) {
                        self::append($component, SiteFooterEditorState::column());
                    }
                }))
                ->cloneable()->cloneAction(fn (Action $action): Action => $action->label('کپی ستون و عناصر')->action(function (array $arguments, Repeater $component): void {
                    $items = $component->getState() ?? [];
                    $column = $items[$arguments['item']] ?? null;
                    if (! $column || count($items) >= SiteFooterCompositionNormalizer::MAX_COLUMNS || self::elementCount($component) + count($column['elements'] ?? []) > SiteFooterCompositionNormalizer::MAX_ELEMENTS) {
                        self::limitWarning();

                        return;
                    }
                    self::append($component, SiteFooterEditorState::copyColumn($column));
                }))
                ->deleteAction(fn (Action $action): Action => $action->requiresConfirmation()->modalHeading('حذف ستون و همه عناصر آن؟')->modalDescription('این تغییر پس از ذخیره اعمال می‌شود.'))
                ->reorderableWithButtons()->collapsible()->itemNumbers()
                ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? $state['title'] : 'ستون بدون عنوان')
                ->helperText('تا ۸ ستون، ۱۲ عنصر در هر ستون و ۴۸ عنصر در کل فوتر. ستون خالی در سایت نمایش داده نمی‌شود.')
                ->columnSpanFull(),
            self::elements('content.composition.bottom.elements', true),
        ];
    }

    private static function elements(string $path, bool $bottom): Repeater
    {
        $labels = $bottom ? SiteFooterEditorState::BOTTOM_LABELS : SiteFooterEditorState::MAIN_LABELS;
        $repeater = Repeater::make($path)->label($bottom ? 'نوار پایین فوتر' : 'عناصر ستون')
            ->schema([
                Hidden::make('id'), Hidden::make('type'),
                Toggle::make('enabled')->label('نمایش در سایت')->default(true)->live(),
                Placeholder::make('element_status')->label('وضعیت نمایش')->content(fn (Get $get): string => SiteFooterEditorState::diagnostic($get('') ?? [])),
                Group::make()->schema(fn (Get $get): array => self::elementFields((string) $get('type')))
                    ->extraAttributes(fn (Get $get): array => ['data-footer-element-id' => $get('id'), 'tabindex' => '-1']),
                Placeholder::make('element_identity')->label('شناسه ثابت عنصر')->content(fn (Get $get): string => (string) $get('id')),
            ])->defaultItems(0)->maxItems($bottom ? SiteFooterCompositionNormalizer::MAX_ELEMENTS : SiteFooterCompositionNormalizer::MAX_ELEMENTS_PER_COLUMN)
            ->addActionLabel($bottom ? 'افزودن عنصر به نوار پایین' : 'افزودن عنصر')
            ->addAction(fn (Action $action): Action => $action
                ->modalHeading('افزودن عنصر فوتر')->form([Select::make('element_type')->label('نوع عنصر')->options($labels)->required()->native(false)])
                ->action(function (array $data, Repeater $component) use ($labels, $bottom): void {
                    $type = $data['element_type'] ?? '';
                    if (! array_key_exists($type, $labels)) {
                        return;
                    }
                    if (self::elementCount($component) >= SiteFooterCompositionNormalizer::MAX_ELEMENTS || (! $bottom && count($component->getState() ?? []) >= SiteFooterCompositionNormalizer::MAX_ELEMENTS_PER_COLUMN)) {
                        self::limitWarning();

                        return;
                    }
                    self::append($component, SiteFooterEditorState::element($type));
                }))
            ->deleteAction(fn (Action $action): Action => $action->requiresConfirmation()->modalHeading('حذف عنصر؟')->modalDescription('حذف پس از ذخیره اعمال می‌شود. برای پنهان‌کردن موقت، نمایش در سایت را خاموش کنید.'))
            ->reorderableWithButtons()->collapsible()->itemLabel(fn (array $state): string => SiteFooterEditorState::label($state))
            ->live(debounce: 700)->columnSpanFull();
        if (! $bottom) {
            $repeater->extraItemActions([
                Action::make('moveToColumn')->label('انتقال به ستون دیگر')->icon('heroicon-o-arrow-right-on-rectangle')
                    ->modalHeading('انتقال عنصر به ستون دیگر')
                    ->form(fn (Repeater $component): array => [Select::make('column_id')->label('ستون مقصد')->options(self::destinations($component))->required()->native(false)->helperText('عنصر به انتهای ستون مقصد منتقل می‌شود؛ شناسه آن ثابت می‌ماند.')])
                    ->action(function (array $data, array $arguments, Repeater $component): void {
                        $path = self::compositionPath($component).'.main.columns';
                        $columns = data_get($component->getLivewire(), $path, []);
                        $sourceKey = Str::afterLast(Str::beforeLast($component->getStatePath(), '.elements'), '.');
                        $element = $columns[$sourceKey]['elements'][$arguments['item']] ?? null;
                        $targetKey = null;
                        foreach ($columns as $key => $column) {
                            if (($column['id'] ?? null) === ($data['column_id'] ?? null) && (string) $key !== $sourceKey) {
                                $targetKey = $key;
                            }
                        }
                        if ($element === null || $targetKey === null || count($columns[$targetKey]['elements'] ?? []) >= SiteFooterCompositionNormalizer::MAX_ELEMENTS_PER_COLUMN) {
                            Notification::make()->title('انتقال انجام نشد')->body('ستون مقصد باید متفاوت باشد و کمتر از ۱۲ عنصر داشته باشد.')->warning()->send();

                            return;
                        }
                        unset($columns[$sourceKey]['elements'][$arguments['item']]);
                        $columns[$targetKey]['elements'][(string) Str::uuid()] = $element;
                        $livewire = $component->getLivewire();
                        data_set($livewire, $path, $columns);
                        $component->callAfterStateUpdated();
                        $component->getLivewire()->dispatch('footer-element-moved', id: $element['id'], footerPath: Str::before(self::compositionPath($component), '.content.composition'));
                        Notification::make()->title('عنصر به انتهای ستون مقصد منتقل شد')->body('برای اعمال در سایت، ذخیره کنید.')->success()->send();
                    }),
            ]);
        }

        return $repeater;
    }

    private static function append(Repeater $component, array $row): void
    {
        $key = (string) Str::uuid();
        $component->state([...($component->getState() ?? []), $key => $row]);
        $component->getChildComponentContainer($key)->fill($row);
        $component->collapsed(false, shouldMakeComponentCollapsible: false);
        $component->callAfterStateUpdated();
    }

    private static function refillBlock(Component $component, array $state): void
    {
        $container = $component->getContainer();
        while (($parent = $container->getParentComponent()) !== null && $parent->getContainer()->getStatePath() === $container->getStatePath()) {
            $container = $parent->getContainer();
        }
        $container->fill($state);
    }

    private static function compositionPath(Component $component): string
    {
        return Str::before($component->getStatePath(), '.content.composition').'.content.composition';
    }

    private static function elementCount(Component $component): int
    {
        $composition = data_get($component->getLivewire(), self::compositionPath($component), []);

        return count($composition['bottom']['elements'] ?? []) + array_sum(array_map(fn (array $column): int => count($column['elements'] ?? []), $composition['main']['columns'] ?? []));
    }

    private static function destinations(Repeater $component): array
    {
        $columns = data_get($component->getLivewire(), self::compositionPath($component).'.main.columns', []);
        $sourceKey = Str::afterLast(Str::beforeLast($component->getStatePath(), '.elements'), '.');
        $options = [];
        foreach (array_keys($columns) as $index => $key) {
            $column = $columns[$key];
            if ((string) $key !== $sourceKey && count($column['elements'] ?? []) < SiteFooterCompositionNormalizer::MAX_ELEMENTS_PER_COLUMN) {
                $options[$column['id']] = 'ستون '.($index + 1).(filled($column['title'] ?? null) ? ' · '.$column['title'] : ' · بدون عنوان');
            }
        }

        return $options;
    }

    private static function limitWarning(): void
    {
        Notification::make()->title('محدودیت ظرفیت فوتر')->body('حداکثر ۸ ستون، ۱۲ عنصر در هر ستون و ۴۸ عنصر در کل فوتر مجاز است.')->warning()->send();
    }

    private static function elementFields(string $type): array
    {
        $source = in_array($type, ['branding', 'contact', 'social_links', 'rich_text', 'copyright'], true) ? [
            Select::make('content.source')->label('منبع محتوا')->options(['site' => 'از تنظیمات سایت', 'manual' => 'کاملاً دستی', 'override' => 'اختصاصی با ارث‌بری'])->required()->live()->native(false)
                ->helperText('سایت: داده زنده تنظیمات. دستی: بدون ارث‌بری. اختصاصی: مقدار خالی از تنظیمات ارث می‌برد؛ برای پنهان‌کردن، عنصر را غیرفعال کنید.'),
            Placeholder::make('inherited_values')->label('مقادیر فعلی تنظیمات سایت (فقط خواندنی)')->content(fn () => SiteFooterEditorState::inherited($type)),
        ] : [];
        $manual = fn (Get $get): bool => $get('content.source') !== 'site';
        $fields = match ($type) {
            'branding' => [
                self::text('content.name', 'نام کسب‌وکار')->visible($manual)->dehydratedWhenHidden(),
                self::media('content.logo_media_id', 'لوگو')->visible($manual)->dehydratedWhenHidden(),
                self::text('content.logo_alt', 'متن جایگزین لوگو')->visible($manual)->dehydratedWhenHidden(),
            ],
            'navigation' => [self::text('content.title', 'عنوان منو'), self::menu(), Select::make('content.depth')->label('عمق نمایش')->options(self::numbers(3))->required()->native(false)],
            'contact' => [
                self::text('content.title', 'عنوان بخش تماس'),
                Select::make('content.fields')->label('فیلدهای قابل نمایش')->multiple()->options(SiteFooterEditorState::CONTACT_LABELS)->native(false)
                    ->helperText('برای تغییر ترتیب، گزینه را حذف و دوباره انتخاب کنید؛ گزینه جدید به انتهای فهرست می‌رود.'),
                ...array_map(fn (string $field): Component => ($field === 'address' ? Textarea::make('content.overrides.'.$field)->rows(3)->maxLength(2000) : TextInput::make('content.overrides.'.$field)->maxLength(255))
                    ->label(SiteFooterEditorState::CONTACT_LABELS[$field].' اختصاصی')->visible($manual)->dehydratedWhenHidden()
                    ->extraInputAttributes(in_array($field, ['phone', 'mobile', 'email'], true) ? ['dir' => 'ltr'] : [])
                    ->helperText('در حالت اختصاصی، خالی یعنی ارث‌بری؛ در حالت دستی، خالی یعنی عدم نمایش.'), array_keys(SiteFooterEditorState::CONTACT_LABELS)),
            ],
            'social_links' => [self::links(true)->visible($manual)->dehydratedWhenHidden()->helperText('در حالت اختصاصی، فهرست غیرخالی جایگزین کل شبکه‌ها می‌شود؛ فهرست خالی از سایت ارث می‌برد.')],
            'rich_text' => [self::text('content.title', 'عنوان'), RichEditor::make('content.text')->label('متن')->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])->extraInputAttributes(['dir' => 'rtl'])->maxLength(20000)->visible($manual)->dehydratedWhenHidden()->helperText('متن با پالایشگر مشترک King نمایش داده می‌شود؛ Script و Embed تعاملی مجاز نیست.')],
            'image' => [self::text('content.title', 'عنوان تصویر'), self::media('content.media_id', 'تصویر / نشان اعتماد'), self::text('content.alt', 'متن جایگزین تصویر'), self::action('content.action', 'لینک اختیاری تصویر')],
            'map' => [Hidden::make('content.mode'), self::text('content.title', 'عنوان نقشه'), self::media('content.media_id', 'تصویر ثابت نقشه'), self::text('content.alt', 'توضیح تصویر نقشه'), self::action('content.directions_action', 'مقصد مسیریابی'), Placeholder::make('map_help')->label('رفتار جایگزین')->content('بدون تصویر، لینک معتبر مسیریابی نمایش داده می‌شود. بدون مقصد، تصویر به‌تنهایی نمایش داده می‌شود. iframe و نقشه تعاملی در این فاز وجود ندارد.')],
            'copyright' => [Textarea::make('content.text')->label('متن اختصاصی حق نشر')->maxLength(20000)->rows(2)->visible($manual)->dehydratedWhenHidden(), Toggle::make('content.show_year')->label('نمایش سال جاری')->helperText('سال هنگام نمایش محاسبه می‌شود؛ در متن ذخیره نمی‌شود.')],
            'legal_links' => [self::links(false)],
            // Preserve supported data that is deliberately outside this editor phase.
            default => [Hidden::make('content'), Placeholder::make('unsupported_element')->label('عنصر خارج از محدوده')->content('داده این عنصر بدون تغییر حفظ می‌شود. افزودن یا ویرایش فرم در این فاز ارائه نمی‌شود؛ می‌توانید آن را غیرفعال یا حذف کنید.')],
        };

        return [...$source, ...$fields];
    }

    private static function text(string $path, string $label): TextInput
    {
        return TextInput::make($path)->label($label)->maxLength(255);
    }

    private static function media(string $path, string $label): ViewField
    {
        return ViewField::make($path)->label($label)->view('filament.forms.components.media-library-picker')
            ->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])
            ->helperText(fn (ViewField $component): string => SiteFooterEditorState::mediaWarning($component->getState()))
            ->dehydrateStateUsing(fn (mixed $state): mixed => blank($state) ? null : $state)->live()->columnSpanFull();
    }

    private static function menu(): Select
    {
        return Select::make('content.menu_id')->label('منوی سایت')
            ->options(function (Get $get): array {
                $id = $get('content.menu_id');
                $options = Menu::query()->where(fn ($query) => $query->where('status', 'active')->orWhere('id', $id))->orderBy('title')->pluck('title', 'id')->all();
                if (filled($id) && ! array_key_exists($id, $options)) {
                    $options[$id] = 'منوی ناموجود · '.$id;
                }

                return $options;
            })
            ->getOptionLabelUsing(fn ($value): string => Menu::query()->find($value)?->title ?? 'منوی ناموجود · '.$value)
            ->searchable()->preload()->native(false)->live()
            ->helperText(function (Get $get): string {
                $id = $get('content.menu_id');
                if (blank($id)) {
                    return 'منویی انتخاب نشده؛ این عنصر در سایت پنهان می‌ماند.';
                }
                $menu = Menu::query()->find($id);

                return ! $menu || $menu->status !== 'active' ? 'هشدار: منوی انتخاب‌شده حذف یا غیرفعال شده است؛ مرجع تا انتخاب شما حفظ می‌شود.' : 'فقط آیتم‌های معتبر و قابل نمایش منو رندر می‌شوند.';
            })->dehydrateStateUsing(fn ($state) => blank($state) ? null : $state);
    }

    private static function action(string $path, string $label): Group
    {
        return Group::make()->schema(fn (Get $get): array => ($get($path)['type'] ?? null) === 'form'
            ? [
                Hidden::make($path),
                Placeholder::make(str_replace('.', '_', $path).'_status')->label($label)->content('مقصد فرم موجود بدون تغییر حفظ می‌شود، اما در این فاز رندر یا ویرایش نمی‌شود.'),
                Actions::make([
                    Action::make('remove_'.str_replace('.', '_', $path))->label('حذف صریح مقصد فرم')->color('danger')->requiresConfirmation()
                        ->action(fn (Set $set) => $set($path, null)),
                ]),
            ]
            : [
                ActionPicker::make($path)->label($label)->allowedTypes(SiteFooterEditorState::ACTION_TYPES)->columnSpanFull(),
                Placeholder::make(str_replace('.', '_', $path).'_status')->label('وضعیت مقصد')->content(fn (Get $get): string => SiteFooterEditorState::actionWarning($get($path))),
            ])->columnSpanFull();
    }

    private static function links(bool $social): Repeater
    {
        return Repeater::make('content.items')->label($social ? 'شبکه‌های اختصاصی' : 'لینک‌های حقوقی')
            ->schema([
                self::text('label', 'عنوان لینک'),
                ...($social ? [self::iconsaxIconPicker('icon', 'آیکن شبکه (اختیاری)')] : []),
                self::action('action', 'مقصد لینک'),
                Placeholder::make('link_warning')->label('وضعیت لینک')->content(function (Get $get): string {
                    $action = $get('action');
                    if (blank($get('label')) || blank($action['type'] ?? null)) {
                        return 'هشدار: عنوان و مقصد معتبر لازم است؛ لینک ناقص در سایت نمایش داده نمی‌شود.';
                    }

                    return app(ActionPickerService::class)->validationMessage($action, false, SiteFooterEditorState::ACTION_TYPES)
                        ?? 'مقصد انتخاب شده است؛ نمایش عمومی به انتشار و دسترسی مقصد بستگی دارد.';
                }),
            ])->defaultItems(0)->maxItems(20)->reorderableWithButtons()->collapsible()
            ->itemLabel(fn (array $state): string => $state['label'] ?? 'لینک بدون عنوان')->addActionLabel('افزودن لینک')->columnSpanFull();
    }
}
