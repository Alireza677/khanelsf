<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\FormResource\Pages;
use App\Models\Form as FormModel;
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
                    'image_choice' => ['label' => 'انتخاب تصویری', 'icon' => 'heroicon-o-photo'],
                    'radio_card' => ['label' => 'کارت انتخابی', 'icon' => 'heroicon-o-check-circle'],
                ],
            ],
            'advanced' => [
                'label' => 'فیلدهای پیشرفته',
                'fields' => [
                    'email' => ['label' => 'ایمیل', 'icon' => 'heroicon-o-envelope'],
                    'tel' => ['label' => 'تلفن', 'icon' => 'heroicon-o-phone'],
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

    public static function prepareSchemaForEditor(array $data): array
    {
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
            if (! is_array($field) || ! in_array($field['type'] ?? null, ['select', 'image_choice', 'radio_card'], true)) {
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
                }
            }
        }

        data_set(
            $data,
            'schema.fields',
            app(FormSchemaIdentityManager::class)->canonicalize($fields),
        );

        return $data;
    }

    public static function prepareSchemaForStorage(array $data): array
    {
        $fields = data_get($data, 'schema.fields', []);
        $fields = is_array($fields) ? $fields : [];
        $isCalculator = data_get($data, 'type') === 'calculator';

        if ($isCalculator) {
            data_set(
                $data,
                'schema.calculator.recommendations',
                static::recommendationsForStorage(data_get($data, 'schema.calculator.recommendations', [])),
            );
        }

        foreach ($fields as $fieldIndex => $field) {
            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $optionIndex => $option) {
                if ($isCalculator || array_key_exists('scores', $option)) {
                    $fields[$fieldIndex]['options'][$optionIndex]['scores'] = static::scoresForStorage($option['scores'] ?? []);
                }
            }
        }

        data_set($data, 'schema.fields', app(FormSchemaIdentityManager::class)->canonicalize($fields));

        return $data;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
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
                    Forms\Components\Select::make('display_mode')
                        ->label('نحوه نمایش')
                        ->required()
                        ->options([
                            'page' => 'صفحه مستقل',
                            'modal' => 'مودال',
                        ])
                        ->default('page'),
                    Forms\Components\Select::make('type')
                        ->label('نوع فرم')
                        ->required()
                        ->live()
                        ->options([
                            'normal' => 'فرم عادی',
                            'calculator' => 'فرم محاسبه‌گر',
                        ])
                        ->default('normal'),
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
                ->description('ترتیب فیلدها همان ترتیب نمایش است. با «شروع مرحله» فرم را به چند صفحه تقسیم کنید.')
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
                                ->options([
                                    'text' => 'متن',
                                    'email' => 'ایمیل',
                                    'tel' => 'تلفن',
                                    'textarea' => 'متن چندخطی',
                                    'select' => 'فهرست انتخاب',
                                    'image_choice' => 'انتخاب تصویری',
                                    'radio_card' => 'کارت انتخابی',
                                    'page' => 'شروع مرحله / صفحه',
                                    'step' => 'شروع مرحله (نام جایگزین)',
                                ])
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
                                ->columnSpanFull(),
                            Repeater::make('options')
                                ->label('گزینه‌ها')
                                ->visible(fn (Forms\Get $get): bool => in_array($get('type'), ['select', 'image_choice', 'radio_card'], true))
                                ->schema([
                                    Forms\Components\Hidden::make('option_id')
                                        ->default(fn (): string => strtoupper((string) Str::ulid())),
                                    Forms\Components\Hidden::make('value'),
                                    Forms\Components\TextInput::make('label')
                                        ->label('عنوان گزینه')
                                        ->live(debounce: 300)
                                        ->required(),
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
                                        ->visible(fn (Forms\Get $get, $livewire): bool => in_array($get('../../type'), ['image_choice', 'radio_card'], true)
                                            && data_get($livewire, 'data.type') === 'calculator')
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
                            'fieldTypeLabels' => static::fieldTypeLabels(),
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
                                'layout' => ['span' => 12],
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
                            $component->getLivewire()->dispatch('form-builder-field-added', key: $newUuid);
                        }))
                        ->cloneable()
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('نتایج محاسبه')
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'calculator')
                ->schema([
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
            Forms\Components\Section::make('پیام‌ها')
                ->schema([
                    Forms\Components\TextInput::make('settings.submit_label')
                        ->label('متن دکمه ارسال')
                        ->default('ارسال'),
                    Forms\Components\TextInput::make('settings.success_message')
                        ->label('پیام موفقیت')
                        ->default('اطلاعات شما با موفقیت دریافت شد.'),
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
        ]);
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
                Tables\Columns\TextColumn::make('display_mode')
                    ->label('نحوه نمایش')
                    ->formatStateUsing(fn (string $state): string => $state === 'modal' ? 'مودال' : 'صفحه')
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
