<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\CalculatorPerformanceMatrix;
use App\Filament\Support\CalculatorDecisionReportEditor;
use App\Filament\Support\CalculatorResultContentEditor;
use App\Filament\Support\CalculatorWeightedEditor;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\FormResource\Pages;
use App\Models\Form as FormModel;
use App\Services\Calculators\CalculatorEligibilityRuleSchema;
use App\Services\Calculators\CalculatorScoringSchema;
use App\Services\FormSchema;
use App\Services\FormSchemaIdentityManager;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class FormResource extends Resource
{
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    private const SYSTEM_MANAGED_CHOICE_VALUE_TYPES = ['select', 'radio', 'checkbox', 'image_choice'];

    protected static ?string $model = FormModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $slug = 'crm/forms';

    protected static ?int $navigationSort = 3;

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $childItems = [
            NavigationItem::make('نمایش همه فرم‌ها')
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*'))
                ->url(static::getUrl('index')),
        ];

        if (FormSubmissionResource::canAccess()) {
            $childItems[] = NavigationItem::make(FormSubmissionResource::getNavigationLabel())
                ->isActiveWhen(fn (): bool => request()->routeIs(FormSubmissionResource::getRouteBaseName().'.*'))
                ->url(FormSubmissionResource::getUrl('index'));
        }

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->routeIs([
                    static::getRouteBaseName().'.*',
                    FormSubmissionResource::getRouteBaseName().'.*',
                ]))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(null)
                ->childItems($childItems),
        ];
    }

    public static function fieldPalette(): array
    {
        return [
            'standard' => [
                'label' => 'فیلدهای استاندارد',
                'fields' => [
                    'text' => ['label' => 'متن کوتاه', 'icon' => 'heroicon-o-pencil-square'],
                    'number' => ['label' => 'عدد', 'icon' => 'heroicon-o-hashtag'],
                    'textarea' => ['label' => 'متن چندخطی', 'icon' => 'heroicon-o-bars-3-bottom-left'],
                ],
            ],
            'structural' => [
                'label' => 'فیلدهای ساختاری',
                'fields' => [
                    'page' => ['label' => 'شروع مرحله / صفحه', 'icon' => 'heroicon-o-rectangle-stack'],
                    'step' => ['label' => 'شروع مرحله', 'icon' => 'heroicon-o-queue-list'],
                ],
            ],
            'choice' => [
                'label' => 'فیلدهای انتخابی',
                'fields' => [
                    'select' => ['label' => 'فهرست انتخاب', 'icon' => 'heroicon-o-chevron-up-down'],
                    'radio' => ['label' => 'رادیویی', 'icon' => 'heroicon-o-list-bullet'],
                    'checkbox' => ['label' => 'انتخاب چندگانه', 'icon' => 'heroicon-o-check-badge'],
                    'image_choice' => ['label' => 'انتخاب تصویری', 'icon' => 'heroicon-o-photo'],
                ],
            ],
            'advanced' => [
                'label' => 'فیلدهای پیشرفته',
                'fields' => [
                    'email' => ['label' => 'ایمیل', 'icon' => 'heroicon-o-envelope'],
                    'tel' => ['label' => 'تلفن', 'icon' => 'heroicon-o-phone'],
                    'date' => ['label' => 'انتخاب تاریخ', 'icon' => 'heroicon-o-calendar-days'],
                    'file' => ['label' => 'آپلود فایل', 'icon' => 'heroicon-o-paper-clip'],
                ],
            ],
        ];
    }

    public static function fieldTypeLabels(): array
    {
        return collect(static::fieldPalette())
            ->pluck('fields')
            ->collapse()
            ->mapWithKeys(fn (array $field, string $type): array => [$type => $field['label']])
            ->all();
    }

    public static function supportedFieldTypeLabels(): array
    {
        return [
            ...static::fieldTypeLabels(),
            'radio_card' => 'کارت انتخابی (قدیمی)',
        ];
    }

    private static function newChoiceOptionValue(): string
    {
        return 'option_'.strtolower((string) Str::ulid());
    }

    private static function hasSystemManagedChoiceValue(mixed $type): bool
    {
        return in_array($type, self::SYSTEM_MANAGED_CHOICE_VALUE_TYPES, true);
    }

    public static function prepareSchemaForEditor(array $data): array
    {
        foreach (['show_hero', 'show_stepper', 'show_step_counter', 'show_step_description'] as $key) {
            data_set($data, 'settings.presentation.'.$key, data_get($data, 'settings.presentation.'.$key) ?? true);
        }

        if (($data['type'] ?? null) === 'calculator') {
            $data['schema'] = app(CalculatorScoringSchema::class)->normalize(
                CalculatorWeightedEditor::pruneReferences($data['schema'] ?? []), 'data.schema',
            );
        }

        $fields = data_get($data, 'schema.fields', []);
        $fields = is_array($fields) ? $fields : [];
        $isCalculator = data_get($data, 'type') === 'calculator';

        if ($isCalculator) {
            $recommendations = static::recommendationsForEditor(
                data_get($data, 'schema.calculator.recommendations', []),
            );
            data_set($data, 'schema.calculator.recommendations', $recommendations);
        }

        foreach ($fields as $index => $field) {
            if (is_array($field) && ($field['type'] ?? null) === 'date' && ! array_key_exists('date_range_enabled', $field)) {
                $fields[$index]['date_range_enabled'] = filled($field['min_date'] ?? null) || filled($field['max_date'] ?? null);
            }

            if (! is_array($field) || ! in_array($field['type'] ?? null, ['select', 'radio', 'checkbox', 'image_choice', 'radio_card'], true)) {
                continue;
            }

            $options = [];
            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $value => $option) {
                $options[] = is_string($option)
                    ? ['value' => $value, 'label' => $option]
                    : $option;
            }

            $fields[$index]['options'] = $options;

            foreach ($options as $optionIndex => $option) {
                if ($isCalculator && is_array($option)) {
                    $fields[$index]['options'][$optionIndex]['scores'] = static::scoresForEditor($option['scores'] ?? []);
                    if (array_key_exists('criterion_weights', $option)) {
                        $fields[$index]['options'][$optionIndex]['criterion_weights'] = CalculatorWeightedEditor::effectsForEditor($option['criterion_weights']);
                    }
                }
            }
        }

        $fields = app(FormSchemaIdentityManager::class)->canonicalize($fields);
        data_set($data, 'schema.fields', $fields);

        if ($isCalculator) {
            data_set(
                $data,
                'schema.calculator.eligibility_rules',
                app(CalculatorEligibilityRuleSchema::class)->normalize(
                    $fields,
                    data_get($data, 'schema.calculator.recommendations', []),
                    data_get($data, 'schema.calculator.eligibility_rules', []),
                ),
            );
        }

        return $data;
    }

    public static function prepareSchemaForStorage(array $data): array
    {
        $fields = data_get($data, 'schema.fields', []);
        $fields = is_array($fields) ? $fields : [];
        $isCalculator = data_get($data, 'type') === 'calculator';

        if ($isCalculator) {
            // Hidden repeaters retain their UUID row keys until this boundary.
            $criteria = data_get($data, 'schema.calculator.criteria');
            if (is_array($criteria)) {
                data_set($data, 'schema.calculator.criteria', array_values($criteria));
            }
            data_set(
                $data,
                'schema.calculator.recommendations',
                static::recommendationsForStorage(data_get($data, 'schema.calculator.recommendations', [])),
            );
        }

        foreach ($fields as $fieldIndex => $field) {
            if (($field['type'] ?? null) === 'date') {
                $fields[$fieldIndex]['min_date'] = FormSchema::normalizeIsoDate($field['min_date'] ?? null);
                $fields[$fieldIndex]['max_date'] = FormSchema::normalizeIsoDate($field['max_date'] ?? null);
                $fields[$fieldIndex]['date_range_enabled'] = array_key_exists('date_range_enabled', $field)
                    ? filter_var($field['date_range_enabled'], FILTER_VALIDATE_BOOLEAN)
                    : $fields[$fieldIndex]['min_date'] !== null || $fields[$fieldIndex]['max_date'] !== null;
            }

            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $optionIndex => $option) {
                if ($isCalculator || array_key_exists('scores', $option)) {
                    $fields[$fieldIndex]['options'][$optionIndex]['scores'] = static::scoresForStorage($option['scores'] ?? []);
                }
                if ($isCalculator && array_key_exists('criterion_weights', $option)) {
                    $fields[$fieldIndex]['options'][$optionIndex]['criterion_weights'] = CalculatorWeightedEditor::effectsForStorage(
                        $option['criterion_weights'], "data.schema.fields.{$fieldIndex}.options.{$optionIndex}.criterion_weights",
                    );
                }
            }
        }

        $fields = app(FormSchemaIdentityManager::class)->canonicalize($fields);
        data_set($data, 'schema.fields', $fields);

        if ($isCalculator) {
            data_set(
                $data,
                'schema.calculator.eligibility_rules',
                app(CalculatorEligibilityRuleSchema::class)->normalize(
                    $fields,
                    data_get($data, 'schema.calculator.recommendations', []),
                    data_get($data, 'schema.calculator.eligibility_rules', []),
                ),
            );
        }

        if ($isCalculator) {
            $data['schema'] = app(CalculatorScoringSchema::class)->normalize($data['schema'], 'data.schema');
        }

        return $data;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('form_tabs')->tabs([
                Forms\Components\Tabs\Tab::make('ساختار و تنظیمات فرم')->schema(static::definitionSchema()),
                Forms\Components\Tabs\Tab::make('نمایش و صفحه فرم')->schema([
                    Forms\Components\Section::make('Hero')
                        ->description('این تنظیمات فقط در صفحه مستقل فرم استفاده می‌شوند. انتخاب صفحه یا مودال همچنان در Action انجام می‌شود.')
                        ->schema([
                            Forms\Components\Toggle::make('settings.presentation.show_hero')
                                ->label('نمایش Hero')->default(true),
                            Forms\Components\TextInput::make('settings.presentation.title')
                                ->label('عنوان عمومی صفحه')->maxLength(255)
                                ->helperText('در صورت خالی بودن، نام داخلی فرم نمایش داده می‌شود.'),
                            Forms\Components\Textarea::make('settings.presentation.description')
                                ->label('توضیح کوتاه')->rows(3)->maxLength(2000),
                            Forms\Components\TextInput::make('settings.presentation.eyebrow')
                                ->label('متن بالای عنوان')->maxLength(255),
                            Forms\Components\ViewField::make('settings.presentation.hero_media_id')
                                ->label('تصویر Hero از کتابخانه رسانه')
                                ->view('filament.forms.components.media-library-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                                ->columnSpanFull(),
                        ])->columns(2),
                    Forms\Components\Section::make('تجربه فرم')->schema([
                        Forms\Components\Toggle::make('settings.presentation.show_stepper')
                            ->label('نمایش Stepper')->default(true),
                        Forms\Components\Toggle::make('settings.presentation.show_step_counter')
                            ->label('نمایش مرحله X از Y')->default(true),
                        Forms\Components\Toggle::make('settings.presentation.show_step_description')
                            ->label('نمایش توضیح سؤال / مرحله')->default(true),
                        Forms\Components\TextInput::make('settings.presentation.previous_button_label')
                            ->label('متن دکمه مرحله قبل')->placeholder('مرحله قبل')->maxLength(100),
                        Forms\Components\TextInput::make('settings.presentation.next_button_label')
                            ->label('متن دکمه مرحله بعد')->placeholder('مرحله بعد')->maxLength(100),
                    ])->columns(2),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    private static function definitionSchema(): array
    {
        return [
            Forms\Components\Section::make('تعریف فرم')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Forms\Set $set): mixed => $set('slug', Str::slug($state ?? ''))),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('status')
                        ->required()
                        ->options([
                            'draft' => 'پیش‌نویس',
                            'published' => 'منتشرشده',
                            'archived' => 'بایگانی‌شده',
                        ])
                        ->default('draft'),
                    Forms\Components\Select::make('type')
                        ->label('نوع فرم')
                        ->required()
                        ->live()
                        ->options([
                            'normal' => 'فرم عادی',
                            'calculator' => 'فرم محاسبه‌گر',
                        ])
                        ->default('normal'),
                    Forms\Components\Toggle::make('lead_generation_enabled')
                        ->label('ایجاد سرنخ فروش از ورودی‌ها')
                        ->helperText('در صورت فعال بودن، هر ورودی جدید این فرم طبق فرآیند فعلی CRM به‌عنوان سرنخ فروش پردازش می‌شود.')
                        ->default(false),
                    Forms\Components\TextInput::make('calculator_identifier')
                        ->label('شناسه محاسبه‌گر')
                        ->helperText('یک شناسه پایدار انگلیسی برای گزارش‌ها؛ مثل construction_process_v1')
                        ->regex('/^[a-z][a-z0-9_]*$/')
                        ->required(fn (Forms\Get $get): bool => $get('type') === 'calculator')
                        ->visible(fn (Forms\Get $get): bool => $get('type') === 'calculator'),
                    Forms\Components\Hidden::make('schema_version')->default(FormModel::SCHEMA_VERSION),
                ])
                ->columns(2),
            Forms\Components\Section::make('فیلدها')
                ->description('ترتیب فیلدها همان ترتیب نمایش است. با «شروع مرحله / صفحه» فرم را به چند صفحه تقسیم کنید.')
                ->schema([
                    Repeater::make('schema.fields')
                        ->label('فیلدهای فرم')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\Hidden::make('field_id')
                                ->default(fn (): string => strtoupper((string) Str::ulid())),
                            Forms\Components\Hidden::make('key'),
                            Forms\Components\TextInput::make('label')
                                ->label('عنوان فیلد')
                                ->live(debounce: 300)
                                ->required(),
                            Forms\Components\Select::make('type')
                                ->label('نوع')
                                ->live()
                                ->options(static::supportedFieldTypeLabels())
                                ->required()
                                ->default('text'),
                            Forms\Components\Toggle::make('required')
                                ->label('الزامی')
                                ->live()
                                ->hidden(fn (Forms\Get $get): bool => in_array($get('type'), ['page', 'step'], true))
                                ->default(false),
                            Forms\Components\TextInput::make('placeholder')
                                ->label('متن راهنما')
                                ->hidden(fn (Forms\Get $get): bool => in_array($get('type'), ['page', 'step'], true)),
                            Forms\Components\Toggle::make('date_range_enabled')
                                ->label('تعیین محدوده تاریخ')
                                ->default(false)
                                ->live()
                                ->dehydratedWhenHidden()
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'date'),
                            Forms\Components\DatePicker::make('min_date')
                                ->label('حداقل تاریخ')
                                ->jalali()
                                ->format('Y-m-d')
                                ->native(false)
                                ->closeOnDateSelection()
                                ->dehydratedWhenHidden()
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'date' && (bool) $get('date_range_enabled')),
                            Forms\Components\DatePicker::make('max_date')
                                ->label('حداکثر تاریخ')
                                ->jalali()
                                ->format('Y-m-d')
                                ->native(false)
                                ->closeOnDateSelection()
                                ->afterOrEqual('min_date')
                                ->dehydratedWhenHidden()
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'date' && (bool) $get('date_range_enabled')),
                            Forms\Components\Select::make('layout.span')
                                ->label('عرض فیلد')
                                ->options([
                                    12 => 'تمام عرض (۱۰۰٪)',
                                    9 => 'سه‌چهارم (۷۵٪)',
                                    8 => 'دو‌سوم (۶۶٪)',
                                    6 => 'نصف (۵۰٪)',
                                    4 => 'یک‌سوم (۳۳٪)',
                                    3 => 'یک‌چهارم (۲۵٪)',
                                ])
                                ->default(12)
                                ->native(false)
                                ->live()
                                ->hidden(fn (Forms\Get $get): bool => in_array($get('type'), ['page', 'step'], true)),
                            Forms\Components\Textarea::make('description')
                                ->label('توضیح مرحله')
                                ->visible(fn (Forms\Get $get): bool => in_array($get('type'), ['page', 'step'], true))
                                ->dehydratedWhenHidden()
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('settings.thousands_separator')
                                ->label('جداکننده هزارگان')
                                ->default(false)
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'number'),
                            Forms\Components\Toggle::make('settings.allow_decimals')
                                ->label('اعشار')
                                ->default(false)
                                ->live()
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'number'),
                            Forms\Components\Select::make('settings.decimal_places')
                                ->label('تعداد ارقام اعشار')
                                ->options([1 => '۱', 2 => '۲', 3 => '۳', 4 => '۴'])
                                ->default(2)
                                ->required(fn (Forms\Get $get): bool => $get('type') === 'number'
                                    && (bool) $get('settings.allow_decimals'))
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'number'
                                    && (bool) $get('settings.allow_decimals')),
                            Forms\Components\Select::make('settings.file_type')
                                ->label('نوع فایل مجاز')
                                ->options([
                                    'all' => 'فایل و تصویر',
                                    'image' => 'فقط تصویر',
                                    'document' => 'فقط سند',
                                ])
                                ->default('all')
                                ->required(fn (Forms\Get $get): bool => $get('type') === 'file')
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'file')
                                ->native(false),
                            Repeater::make('options')
                                ->label('گزینه‌ها')
                                ->visible(fn (Forms\Get $get): bool => in_array($get('type'), ['select', 'radio', 'checkbox', 'image_choice', 'radio_card'], true))
                                ->schema([
                                    Forms\Components\Hidden::make('option_id')
                                        ->default(fn (): string => strtoupper((string) Str::ulid())),
                                    Forms\Components\Hidden::make('description'),
                                    Forms\Components\Hidden::make('icon'),
                                    Forms\Components\TextInput::make('label')
                                        ->label('عنوان گزینه')
                                        ->live(debounce: 300)
                                        ->required(),
                                    Forms\Components\TextInput::make('value')
                                        ->label('مقدار گزینه')
                                        ->default(fn (Forms\Get $get): ?string => static::hasSystemManagedChoiceValue($get('../../type'))
                                            ? static::newChoiceOptionValue()
                                            : null)
                                        ->hidden(fn (Forms\Get $get): bool => static::hasSystemManagedChoiceValue($get('../../type')))
                                        ->dehydratedWhenHidden()
                                        ->required(fn (Forms\Get $get): bool => ! static::hasSystemManagedChoiceValue($get('../../type')))
                                        ->maxLength(255),
                                    Forms\Components\ViewField::make('image')
                                        ->label('تصویر گزینه')
                                        ->view('filament.forms.components.media-library-url-picker')
                                        ->viewData(fn (): array => [
                                            'images' => static::mediaLibraryImageItems(),
                                        ])
                                        ->visible(fn (Forms\Get $get): bool => in_array($get('../../type'), ['image_choice', 'radio_card'], true)),
                                    Repeater::make('scores')
                                        ->label('امتیازدهی نتایج')
                                        ->schema([
                                            Forms\Components\Select::make('key')
                                                ->label('نتیجه')
                                                ->options(fn ($livewire, Forms\Get $get): array => static::calculatorResultOptions(
                                                    data_get($livewire, 'data.schema.calculator.recommendations', []),
                                                    $get('key'),
                                                ))
                                                ->required(),
                                            Forms\Components\TextInput::make('score')
                                                ->label('امتیاز')
                                                ->numeric()
                                                ->required(),
                                        ])
                                        ->addActionLabel('افزودن امتیاز')
                                        ->reorderable()
                                        ->columns(2)
                                        ->dehydratedWhenHidden(fn ($livewire): bool => data_get($livewire, 'data.type') === 'calculator'
                                            && data_get($livewire, 'data.schema.calculator.scoring_mode') === CalculatorScoringSchema::WEIGHTED)
                                        ->visible(fn (Forms\Get $get, $livewire): bool => in_array($get('../../type'), ['image_choice', 'radio_card', 'radio', 'checkbox'], true)
                                            && data_get($livewire, 'data.type') === 'calculator'
                                            && data_get($livewire, 'data.schema.calculator.scoring_mode', CalculatorScoringSchema::SIMPLE) !== CalculatorScoringSchema::WEIGHTED)
                                        ->columnSpanFull(),
                                    Repeater::make('criterion_weights')
                                        ->label('تأثیر این پاسخ بر معیارها')
                                        ->helperText(fn ($livewire): string => CalculatorWeightedEditor::criteriaOptions(data_get($livewire, 'data.schema.calculator.criteria', [])) === []
                                            ? 'ابتدا در بخش «معیارهای تصمیم‌گیری» معیار اضافه کنید.'
                                            : 'فقط معیارهای مرتبط را اضافه کنید. معیارهای انتخاب‌نشده و وزن‌های خالی معادل صفر هستند.')
                                        ->defaultItems(0)
                                        ->schema([
                                            Forms\Components\Select::make('criterion_id')
                                                ->label('معیار')
                                                ->options(fn ($livewire): array => CalculatorWeightedEditor::criteriaOptions(data_get($livewire, 'data.schema.calculator.criteria', [])))
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'انتخاب معیار الزامی است.',
                                                    'in' => 'معیار انتخاب‌شده دیگر در فهرست معیارها وجود ندارد.',
                                                    'distinct' => 'هر معیار را برای یک پاسخ فقط یک‌بار انتخاب کنید.',
                                                ])
                                                ->native(false),
                                            Forms\Components\TextInput::make('weight')
                                                ->label('میزان تأثیر')
                                                ->numeric()->minValue(0)->maxValue(10)->step('any')
                                                ->default(0)->placeholder('۰')
                                                ->dehydrateStateUsing(fn (mixed $state): mixed => $state === null || $state === '' ? 0 : $state)
                                                ->validationMessages(static::criterionWeightMessages()),
                                        ])
                                        ->addActionLabel('افزودن تأثیر معیار')
                                        ->addable(fn ($livewire): bool => CalculatorWeightedEditor::criteriaOptions(data_get($livewire, 'data.schema.calculator.criteria', [])) !== [])
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state, $livewire): string => CalculatorWeightedEditor::criteriaOptions(data_get($livewire, 'data.schema.calculator.criteria', []))[$state['criterion_id'] ?? ''] ?? 'تأثیر معیار')
                                        ->dehydratedWhenHidden()
                                        ->visible(fn (Forms\Get $get, $livewire): bool => static::isWeightedCalculator($livewire)
                                            && in_array($get('../../type'), ['select', 'image_choice', 'radio_card', 'radio', 'checkbox'], true))
                                        ->columns(2)
                                        ->columnSpanFull(),
                                ])
                                ->view('filament.forms.components.form-builder-choices-editor')
                                ->addActionLabel('افزودن گزینه')
                                ->addAction(fn (Action $action): Action => $action->action(function (Repeater $component): void {
                                    $newUuid = $component->generateUuid();
                                    $items = $component->getState();
                                    $item = [
                                        'label' => 'گزینه جدید',
                                    ];

                                    if ($newUuid) {
                                        $items[$newUuid] = $item;
                                    } else {
                                        $items[] = $item;
                                        $newUuid = array_key_last($items);
                                    }

                                    $component->state($items);
                                    $component->getChildComponentContainer($newUuid)->fill();
                                    $items = $component->getState();
                                    $items[$newUuid] = array_merge($items[$newUuid] ?? [], $item);
                                    $component->state($items);
                                    $component->callAfterStateUpdated();
                                }))
                                ->columns(2)
                                ->columnSpanFull(),
                        ])
                        ->default([
                            ['key' => 'name', 'label' => 'نام', 'type' => 'text', 'required' => true],
                            ['key' => 'phone', 'label' => 'تلفن', 'type' => 'tel', 'required' => false],
                            ['key' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => false],
                            ['key' => 'message', 'label' => 'پیام', 'type' => 'textarea', 'required' => false],
                        ])
                        ->view('filament.forms.components.form-builder-editor', [
                            'fieldPalette' => static::fieldPalette(),
                            'fieldTypeLabels' => static::supportedFieldTypeLabels(),
                        ])
                        ->addAction(fn (Action $action): Action => $action->action(function (array $arguments, Repeater $component): void {
                            $type = array_key_exists($arguments['fieldType'] ?? '', static::fieldTypeLabels())
                                ? $arguments['fieldType']
                                : 'text';
                            $newUuid = $component->generateUuid();
                            $items = $component->getState();
                            $item = [
                                'name' => '',
                                'label' => static::fieldTypeLabels()[$type],
                                'type' => $type,
                                'required' => false,
                                'date_range_enabled' => false,
                                'layout' => ['span' => 12],
                            ];

                            if ($type === 'number') {
                                $item['settings'] = [
                                    'thousands_separator' => false,
                                    'allow_decimals' => false,
                                    'decimal_places' => 2,
                                ];
                            }

                            if ($type === 'file') {
                                $item['settings'] = ['file_type' => 'all'];
                            }

                            if ($newUuid) {
                                $items[$newUuid] = $item;
                            } else {
                                $items[] = $item;
                                $newUuid = array_key_last($items);
                            }

                            $component->state($items);
                            $component->getChildComponentContainer($newUuid)->fill();
                            $items = $component->getState();
                            if (static::hasSystemManagedChoiceValue($type)) {
                                foreach (is_array(data_get($items, "{$newUuid}.options")) ? $items[$newUuid]['options'] : [] as $optionKey => $option) {
                                    if (blank($option['value'] ?? null)) {
                                        $items[$newUuid]['options'][$optionKey]['value'] = static::newChoiceOptionValue();
                                    }
                                }
                            }
                            $items[$newUuid] = array_merge($items[$newUuid] ?? [], $item);
                            $component->state($items);
                            $component->callAfterStateUpdated();
                            $component->getLivewire()->dispatch('form-builder-field-added', key: $newUuid);
                        }))
                        ->cloneable()
                        ->cloneAction(fn (Action $action): Action => $action->after(function (Repeater $component): void {
                            // Filament copies hidden identities too. Assign the clone its own references
                            // before dependent Eligibility selects read the unsaved schema.
                            $items = $component->getState();
                            $cloneKey = array_key_last($items);
                            $items[$cloneKey]['field_id'] = strtoupper((string) Str::ulid());
                            foreach (array_keys($items[$cloneKey]['options'] ?? []) as $optionKey) {
                                $items[$cloneKey]['options'][$optionKey]['option_id'] = strtoupper((string) Str::ulid());
                            }
                            $component->state($items);
                        }))
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('نتایج محاسبه')
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'calculator')
                ->schema([
                    Forms\Components\Select::make('schema.calculator.scoring_mode')
                        ->label('روش محاسبه')
                        ->options([
                            CalculatorScoringSchema::SIMPLE => 'امتیازدهی ساده',
                            CalculatorScoringSchema::WEIGHTED => 'امتیازدهی وزنی چندمعیاره',
                        ])
                        ->default(CalculatorScoringSchema::SIMPLE)
                        ->live()
                        ->formatStateUsing(fn (mixed $state): mixed => $state ?? CalculatorScoringSchema::SIMPLE)
                        ->required()
                        ->validationMessages([
                            'required' => 'انتخاب روش امتیازدهی الزامی است.',
                            'in' => 'روش امتیازدهی معتبر نیست.',
                        ])
                        ->helperText(fn (Forms\Get $get): ?string => $get('schema.calculator.scoring_mode') === CalculatorScoringSchema::WEIGHTED
                            ? 'در این روش، پاسخ‌های کاربر اهمیت معیارهای تصمیم‌گیری را تعیین می‌کنند و هر نتیجه بر اساس عملکرد آن در هر معیار امتیاز نهایی می‌گیرد.'
                            : null)
                        ->extraAttributes(['dir' => 'rtl'])
                        ->native(false),
                    Repeater::make('schema.calculator.recommendations')
                        ->label('نتایج پیشنهادی')
                        ->schema([
                            Forms\Components\Hidden::make('key'),
                            Forms\Components\TextInput::make('label')
                                ->label('عنوان نتیجه')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Forms\Set $set, Forms\Get $get, $livewire): void {
                                    if (is_string($get('key')) && $get('key') !== '') {
                                        return;
                                    }

                                    $set('key', static::nextCalculatorResultKey(
                                        data_get($livewire, 'data.schema.calculator.recommendations', []),
                                        $state ?? '',
                                    ));
                                })
                                ->required(),
                        ])
                        ->addActionLabel('افزودن نتیجه')
                        ->reorderable()
                        ->required()
                        ->columns(1),
                ]),
            Forms\Components\Section::make('معیارهای تصمیم‌گیری')
                ->visible(fn ($livewire): bool => static::isWeightedCalculator($livewire))
                ->dehydratedWhenHidden()
                ->extraAttributes(['dir' => 'rtl'])
                ->schema([
                    Repeater::make('schema.calculator.criteria')
                        ->label('معیارها')
                        ->defaultItems(0)
                        ->schema([
                            Forms\Components\Hidden::make('id')->default(fn (): string => (string) Str::ulid()),
                            Forms\Components\TextInput::make('label')
                                ->label('عنوان معیار')
                                ->placeholder('برای مثال: سرعت اجرا')
                                ->required()->maxLength(255)->live(onBlur: true)
                                ->validationMessages([
                                    'required' => 'عنوان معیار الزامی است.',
                                    'max' => 'عنوان معیار باید حداکثر ۲۵۵ نویسه باشد.',
                                ]),
                            Forms\Components\TextInput::make('base_weight')
                                ->label('وزن پایه')
                                ->helperText('اهمیت اولیهٔ معیار، پیش از درنظرگرفتن پاسخ‌ها؛ از ۰ تا ۱۰.')
                                ->numeric()->minValue(0)->maxValue(10)->step('any')->default(0)->required()
                                ->validationMessages(static::criterionWeightMessages()),
                            Forms\Components\Textarea::make('description')
                                ->label('توضیح معیار (اختیاری)')
                                ->rows(2)->maxLength(2000)->columnSpanFull()
                                ->validationMessages(['max' => 'توضیح معیار باید حداکثر ۲۰۰۰ نویسه باشد.']),
                        ])
                        ->addActionLabel('افزودن معیار')
                        ->deleteAction(fn (Action $action): Action => $action
                            ->label('حذف معیار')->requiresConfirmation()
                            ->modalHeading('حذف معیار تصمیم‌گیری')
                            ->modalDescription('این معیار و امتیازهای وابسته به آن در پاسخ‌ها و ماتریس نتایج حذف می‌شوند. ادامه می‌دهید؟')
                            ->modalSubmitActionLabel('حذف معیار')->modalCancelActionLabel('انصراف'))
                        ->reorderable()->reorderableWithButtons()->collapsible()
                        ->itemLabel(fn (array $state): string => filled($state['label'] ?? null) ? $state['label'] : 'معیار جدید')
                        ->afterStateUpdated(fn ($livewire) => static::pruneWeightedEditorReferences($livewire))
                        ->dehydratedWhenHidden()
                        ->columns(2),
                ]),
            Forms\Components\Section::make('ماتریس امتیاز نتایج')
                ->description('عدد بالاتر یعنی این نتیجه در معیار موردنظر عملکرد بهتری دارد. بازه مجاز از ۰ تا ۵ است.')
                ->visible(fn ($livewire): bool => static::isWeightedCalculator($livewire))
                ->dehydratedWhenHidden()
                ->extraAttributes(['dir' => 'rtl', 'style' => 'min-width: 0;'])
                ->schema([
                    CalculatorPerformanceMatrix::make('schema.calculator.criterion_scores'),
                ]),
            CalculatorResultContentEditor::section(),
            CalculatorDecisionReportEditor::section(),
            Forms\Components\Section::make('قوانین صلاحیت گزینه‌ها')
                ->description('این قوانین مستقل از امتیازدهی هستند و فقط گزینه‌های پیشنهادی را از نتیجه نهایی خارج می‌کنند.')
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'calculator')
                ->schema([
                    Repeater::make('schema.calculator.eligibility_rules')
                        ->label('قوانین Hard Eligibility')
                        ->defaultItems(0)
                        ->schema([
                            Forms\Components\Hidden::make('rule_id')
                                ->default(fn (): string => strtoupper((string) Str::ulid())),
                            Forms\Components\Hidden::make('effect')->default('exclude'),
                            Forms\Components\Select::make('field_id')
                                ->label('فیلد مبنا')
                                ->options(fn (Forms\Get $get): array => static::eligibilityFieldOptions(
                                    $get('data.schema.fields', isAbsolute: true),
                                ))
                                ->live()
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, Forms\Components\Select $component): void {
                                    $fields = $get('data.schema.fields', isAbsolute: true);
                                    $type = static::eligibilityFieldType($fields, $get('field_id'));
                                    if (! array_key_exists((string) $get('operator'), static::eligibilityOperatorOptions($type))) {
                                        $set('operator', null);
                                    }
                                    if (! array_key_exists(strtoupper((string) $get('option_id')), static::eligibilityChoiceOptions($fields, $get('field_id')))) {
                                        $set('option_id', null);
                                    }
                                    $set('number_value', null);
                                    // The enhanced Select is wire:ignore. Refresh even if its state was already null.
                                    foreach (['operator', 'option_id'] as $name) {
                                        $component->getContainer()->getComponent(
                                            fn ($field): bool => $field instanceof Forms\Components\Select && $field->getName() === $name,
                                            withHidden: true,
                                        )?->refreshSelectedOptionLabel();
                                    }
                                })
                                ->required()
                                ->native(false),
                            Forms\Components\Select::make('operator')
                                ->label('عملگر')
                                ->options(fn (Forms\Get $get): array => static::eligibilityOperatorOptions(
                                    static::eligibilityFieldType(
                                        $get('data.schema.fields', isAbsolute: true),
                                        $get('field_id'),
                                    ),
                                ))
                                ->required()
                                ->native(false),
                            Forms\Components\Select::make('option_id')
                                ->label('گزینه مقایسه')
                                ->options(fn (Forms\Get $get): array => static::eligibilityChoiceOptions(
                                    $get('data.schema.fields', isAbsolute: true),
                                    $get('field_id'),
                                ))
                                ->required(fn (Forms\Get $get): bool => app(CalculatorEligibilityRuleSchema::class)->isChoice(
                                    static::eligibilityFieldType(
                                        $get('data.schema.fields', isAbsolute: true),
                                        $get('field_id'),
                                    ),
                                ))
                                ->visible(fn (Forms\Get $get): bool => app(CalculatorEligibilityRuleSchema::class)->isChoice(
                                    static::eligibilityFieldType(
                                        $get('data.schema.fields', isAbsolute: true),
                                        $get('field_id'),
                                    ),
                                ))
                                ->native(false),
                            Forms\Components\TextInput::make('number_value')
                                ->label('مقدار مقایسه')
                                ->numeric()
                                ->required(fn (Forms\Get $get): bool => static::eligibilityFieldType(
                                    $get('data.schema.fields', isAbsolute: true),
                                    $get('field_id'),
                                ) === CalculatorEligibilityRuleSchema::NUMBER_TYPE)
                                ->visible(fn (Forms\Get $get): bool => static::eligibilityFieldType(
                                    $get('data.schema.fields', isAbsolute: true),
                                    $get('field_id'),
                                ) === CalculatorEligibilityRuleSchema::NUMBER_TYPE),
                            Forms\Components\Select::make('profiles')
                                ->label('گزینه‌های خارج‌شونده')
                                ->options(fn ($livewire): array => static::calculatorResultOptions(
                                    data_get($livewire, 'data.schema.calculator.recommendations', []),
                                    null,
                                ))
                                ->multiple()
                                ->minItems(1)
                                ->required()
                                ->native(false),
                            Forms\Components\Textarea::make('reason')
                                ->label('دلیل خارج‌شدن')
                                ->rows(2)
                                ->maxLength(1000)
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel('افزودن قانون')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): string => filled($state['reason'] ?? null)
                            ? str($state['reason'])->limit(70)->toString()
                            : 'قانون جدید')
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('پیام‌ها')
                ->schema([
                    Forms\Components\TextInput::make('settings.submit_label')
                        ->label('متن دکمه ارسال')
                        ->default('ارسال'),
                    Forms\Components\TextInput::make('settings.success_message')
                        ->label('پیام موفقیت')
                        ->default('اطلاعات شما با موفقیت دریافت شد.'),
                    Forms\Components\Toggle::make('settings.submit_confirmation_enabled')
                        ->label('تأیید قبل از ارسال')
                        ->default(false)
                        ->live(),
                    Forms\Components\Textarea::make('settings.submit_confirmation_text')
                        ->label('متن تأیید')
                        ->rows(3)
                        ->maxLength(1000)
                        ->required(fn (Forms\Get $get): bool => (bool) $get('settings.submit_confirmation_enabled'))
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('settings.submit_confirmation_enabled')),
                ])
                ->columns(2),
            Forms\Components\Section::make('اعلان‌ها')
                ->description('در صورت فعال‌سازی، پس از ثبت موفق فرم یک اعلان برای مدیر ارسال می‌شود.')
                ->schema([
                    Forms\Components\Toggle::make('settings.notifications.enabled')
                        ->label('فعال‌سازی اعلان‌ها')
                        ->default(false)
                        ->live(),
                    Forms\Components\Toggle::make('settings.notifications.notify_admin')
                        ->label('ارسال ایمیل به مدیر')
                        ->default(true)
                        ->live()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('settings.notifications.enabled')),
                    Forms\Components\TextInput::make('settings.notifications.email')
                        ->label('ایمیل دریافت‌کننده')
                        ->email()
                        ->maxLength(255)
                        ->required(fn (Forms\Get $get): bool => (bool) $get('settings.notifications.enabled')
                            && (bool) $get('settings.notifications.notify_admin'))
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('settings.notifications.enabled')
                            && (bool) $get('settings.notifications.notify_admin')),
                ])
                ->columns(2),
        ];
    }

    private static function isWeightedCalculator($livewire): bool
    {
        return data_get($livewire, 'data.type') === 'calculator'
            && data_get($livewire, 'data.schema.calculator.scoring_mode') === CalculatorScoringSchema::WEIGHTED;
    }

    private static function criterionWeightMessages(): array
    {
        return [
            'required' => 'واردکردن وزن معیار الزامی است.',
            'numeric' => 'وزن معیار باید عدد باشد.',
            'min' => 'وزن معیار باید بین ۰ تا ۱۰ باشد.',
            'max' => 'وزن معیار باید بین ۰ تا ۱۰ باشد.',
        ];
    }

    private static function pruneWeightedEditorReferences($livewire): void
    {
        $livewire->data['schema'] = CalculatorWeightedEditor::pruneReferences($livewire->data['schema'] ?? [], editorRows: true);
    }

    private static function recommendationsForEditor(mixed $recommendations): array
    {
        $rows = [];

        foreach (is_array($recommendations) ? $recommendations : [] as $key => $recommendation) {
            if (is_array($recommendation)) {
                $rows[] = [
                    'key' => $recommendation['key'] ?? null,
                    'label' => $recommendation['label'] ?? '',
                ];

                continue;
            }

            if (is_string($recommendation)) {
                $rows[] = ['key' => is_string($key) ? $key : null, 'label' => $recommendation];
            }
        }

        return $rows;
    }

    private static function recommendationsForStorage(mixed $recommendations): array
    {
        $stored = [];

        foreach (static::recommendationsForEditor($recommendations) as $recommendation) {
            $label = trim((string) ($recommendation['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $candidate = $recommendation['key'] ?? null;
            $base = is_string($candidate) && preg_match('/^[a-z][a-z0-9_]*$/', $candidate) === 1
                ? $candidate
                : static::calculatorResultKey($label);
            $key = $base;
            $suffix = 2;

            while (array_key_exists($key, $stored)) {
                $key = "{$base}_{$suffix}";
                $suffix++;
            }

            $stored[$key] = $label;
        }

        return $stored;
    }

    private static function scoresForEditor(mixed $scores): array
    {
        $rows = [];

        foreach (is_array($scores) ? $scores : [] as $key => $score) {
            if (is_array($score)) {
                $rows[] = [
                    'key' => $score['key'] ?? null,
                    'score' => $score['score'] ?? 0,
                ];

                continue;
            }

            $rows[] = ['key' => is_string($key) ? $key : null, 'score' => $score];
        }

        return $rows;
    }

    private static function scoresForStorage(mixed $scores): array
    {
        $stored = [];

        foreach (static::scoresForEditor($scores) as $score) {
            $key = $score['key'] ?? null;
            $value = $score['score'] ?? null;

            if (is_string($key) && $key !== '' && is_numeric($value)) {
                $stored[$key] = $value + 0;
            }
        }

        return $stored;
    }

    private static function calculatorResultKey(string $label): string
    {
        $key = strtolower(Str::slug($label, '_'));
        $key = preg_replace('/[^a-z0-9_]+/', '', $key) ?? '';
        $key = trim($key, '_');

        return $key !== '' && preg_match('/^[a-z]/', $key) === 1 ? $key : 'result';
    }

    private static function nextCalculatorResultKey(mixed $recommendations, string $label): string
    {
        $keys = collect(static::recommendationsForEditor($recommendations))
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key))
            ->all();
        $base = static::calculatorResultKey($label);
        $key = $base;
        $suffix = 2;

        while (in_array($key, $keys, true)) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }

    private static function calculatorResultOptions(mixed $recommendations, mixed $currentKey): array
    {
        $options = [];

        foreach (static::recommendationsForEditor($recommendations) as $recommendation) {
            $key = $recommendation['key'] ?? null;
            $label = trim((string) ($recommendation['label'] ?? ''));

            if (is_string($key) && $key !== '' && $label !== '') {
                $options[$key] = $label;
            }
        }

        if (is_string($currentKey) && $currentKey !== '' && ! array_key_exists($currentKey, $options)) {
            $options[$currentKey] = 'نتیجه قدیمی';
        }

        return $options;
    }

    private static function eligibilityFieldOptions(mixed $fields): array
    {
        $options = [];

        foreach (is_array($fields) ? $fields : [] as $field) {
            if (! is_array($field)
                || ! in_array($field['type'] ?? null, CalculatorEligibilityRuleSchema::SUPPORTED_TYPES, true)
                || ! is_string($field['field_id'] ?? null)
                || trim($field['field_id']) === ''
                || ($field['type'] !== CalculatorEligibilityRuleSchema::NUMBER_TYPE
                    && static::eligibilityChoiceOptions([$field], $field['field_id']) === [])) {
                continue;
            }

            $options[strtoupper($field['field_id'])] = (string) ($field['label'] ?? $field['key'] ?? 'فیلد');
        }

        return $options;
    }

    private static function eligibilityFieldType(mixed $fields, mixed $fieldId): ?string
    {
        if (! is_string($fieldId)) {
            return null;
        }

        foreach (is_array($fields) ? $fields : [] as $field) {
            if (is_array($field)
                && strtoupper((string) ($field['field_id'] ?? '')) === strtoupper($fieldId)
                && is_string($field['type'] ?? null)) {
                return $field['type'];
            }
        }

        return null;
    }

    private static function eligibilityChoiceOptions(mixed $fields, mixed $fieldId): array
    {
        if (! is_string($fieldId)) {
            return [];
        }

        foreach (is_array($fields) ? $fields : [] as $field) {
            if (! is_array($field) || strtoupper((string) ($field['field_id'] ?? '')) !== strtoupper($fieldId)) {
                continue;
            }

            $options = [];

            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $option) {
                if (is_array($option) && is_string($option['option_id'] ?? null)
                    && trim($option['option_id']) !== ''
                    && is_string($option['value'] ?? null) && trim($option['value']) !== '') {
                    // Rules store canonical option identities; the evaluator resolves their internal value.
                    $options[strtoupper($option['option_id'])] = (string) ($option['label'] ?? $option['value']);
                }
            }

            return $options;
        }

        return [];
    }

    private static function eligibilityOperatorOptions(?string $type): array
    {
        $labels = [
            'equals' => 'برابر است با',
            'not_equals' => 'برابر نیست با',
            'contains' => 'شامل است',
            'not_contains' => 'شامل نیست',
            'greater_than' => 'بزرگ‌تر از',
            'greater_than_or_equal' => 'بزرگ‌تر یا مساوی',
            'less_than' => 'کوچک‌تر از',
            'less_than_or_equal' => 'کوچک‌تر یا مساوی',
        ];

        return collect(app(CalculatorEligibilityRuleSchema::class)->operatorsFor($type))
            ->mapWithKeys(fn (string $operator): array => [$operator => $labels[$operator]])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['submissions', 'leads']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(fn (string $state): string => $state === 'calculator' ? 'محاسبه‌گر' : 'عادی')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('submissions_count')->label('ورودی‌ها')->sortable(),
                Tables\Columns\TextColumn::make('leads_count')->label('سرنخ‌ها')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->jalaliDateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'پیش‌نویس',
                    'published' => 'منتشرشده',
                    'archived' => 'بایگانی‌شده',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('entries')
                    ->label('ورودی‌ها')
                    ->icon('heroicon-o-inbox-stack')
                    ->url(fn (FormModel $record): string => FormSubmissionResource::getUrl('index', [
                        'tableFilters' => [
                            'form_id' => ['value' => $record->getKey()],
                        ],
                    ])),
                Tables\Actions\Action::make('open')
                    ->label('نمایش فرم')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (FormModel $record): string => route('forms.show', $record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (FormModel $record): bool => $record->status === 'published'),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
    }
}
