<?php

namespace App\Filament\Resources;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Hero\HeroMediaResolver;
use App\CMS\Blocks\Support\HeadingLevel;
use App\CMS\Blocks\Support\TemplateTargets;
use App\Filament\Forms\Components\BlockBuilder;
use App\Filament\Forms\Components\TemplateBlock;
use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\TemplateResource\Pages;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Template;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TemplateResource extends Resource
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = Template::class;

    protected static ?string $navigationGroup = 'Design';

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Template settings'))
                ->description(__('Published default templates replace the built-in layout for their selected type. If no published template exists, the original Blade view is used as fallback.'))
                ->schema([
                    Forms\Components\TextInput::make('title')->label(__('Title'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('slug'))
                            ? $set('slug', Str::slug($state ?? ''))
                            : null),
                    Forms\Components\TextInput::make('slug')->label(__('Slug'))
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('type')->label(__('Type'))
                        ->required()
                        ->options(fn (?Template $record): array => array_map(fn (string $label): string => __($label), Template::editableTypeOptions($record)))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('conditions.item_id', null);
                            $set('conditions.category_id', null);

                            if ($state === 'service_single') {
                                $set('conditions.type', 'all');
                            }
                        }),
                    Forms\Components\Select::make('status')->label(__('Status'))
                        ->required()
                        ->options([
                            'draft' => __('Draft'),
                            'published' => __('Published'),
                        ])
                        ->default('draft'),
                    Forms\Components\Toggle::make('is_default')
                        ->label(__('Default for this type'))
                        ->default(true)
                        ->helperText(__('Only published default templates are used by public dynamic pages.')),
                    Forms\Components\TextInput::make('priority')->label(__('Priority'))
                        ->numeric()
                        ->default(0)
                        ->helperText(__('Higher priority wins when more than one default template exists.')),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Blocks'))
                ->description(__('Use static blocks for fixed sections and dynamic template blocks to render the current post, product, project, gallery, category, or archive collection. Custom Code blocks should be used only by trusted admins.'))
                ->schema([
                    BlockBuilder::make('blocks')
                        ->label(__('Template blocks'))
                        ->templateTarget(fn (Get $get): ?string => $get('type'))
                        ->blocks(fn (Get $get): array => static::blockDefinitions($get('type'), $get('blocks') ?? []))
                        ->cloneable()
                        ->collapsible()
                        ->reorderable()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('Conditions'))
                ->description(__('Specific item templates override category/all templates. Category templates apply to items inside that category. Priority resolves conflicts inside the same specificity level. Draft templates are ignored.'))
                ->schema([
                    Forms\Components\Select::make('conditions.type')
                        ->label(__('Condition type'))
                        ->options(fn (Get $get): array => $get('type') === 'service_single'
                            ? array_intersect_key(array_map(fn (string $label): string => __($label), Template::CONDITION_TYPES), array_flip(['all', 'specific_item']))
                            : array_map(fn (string $label): string => __($label), Template::CONDITION_TYPES))
                        ->default('all')
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('conditions.item_id', null);
                            $set('conditions.category_id', null);
                        })
                        ->helperText(__('Index, header, and footer templates normally use All.')),

                    ...static::conditionSelectors(),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Debug'))
                ->visible(fn (Get $get): bool => app()->environment('local') && $get('type') !== 'service_index')
                ->description(__('Read-only matching hints for this template.'))
                ->schema([
                    Forms\Components\Placeholder::make('debug_type')
                        ->label(__('Template type'))
                        ->content(fn (Get $get): string => __(Template::TYPES[$get('type')] ?? ($get('type') ?: 'Not selected'))),
                    Forms\Components\Placeholder::make('debug_status')
                        ->label(__('Status'))
                        ->content(fn (Get $get): string => __($get('status') === 'published' ? 'Published' : 'Draft')),
                    Forms\Components\Placeholder::make('debug_condition')
                        ->label(__('Condition'))
                        ->content(fn (Get $get): string => static::conditionSummaryFromState($get('conditions') ?? [], (bool) $get('is_default'))),
                    Forms\Components\Placeholder::make('debug_priority')
                        ->label(__('Priority'))
                        ->content(fn (Get $get): string => (string) ($get('priority') ?? 0)),
                    Forms\Components\Placeholder::make('debug_default')
                        ->label(__('Default'))
                        ->content(fn (Get $get): string => $get('is_default') ? __('Yes') : __('No')),
                    Forms\Components\Placeholder::make('debug_match')
                        ->label(__('Can match'))
                        ->content(fn (Get $get): string => static::canMatchSummary((string) $get('type'), $get('conditions') ?? [])),
                    Forms\Components\Placeholder::make('debug_warnings')
                        ->label(__('Warnings'))
                        ->content(fn (Get $get): string => static::debugWarnings(
                            (string) $get('type'),
                            (string) $get('status'),
                            $get('conditions') ?? [],
                            $get('blocks') ?? [],
                        ))
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('debug_specificity')
                        ->label(__('Specificity'))
                        ->content(__('specific item > category > all/default. Priority only resolves conflicts inside the same specificity level.'))
                        ->columnSpanFull(),
                ])
                ->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => __(Template::TYPES[$state] ?? $state))->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('condition_summary')
                    ->label(__('Condition'))
                    ->state(fn (Template $record): string => static::conditionSummaryFromState($record->conditions ?? [], $record->is_default))
                    ->badge(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label(__('Default'))->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->jalaliDateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(array_map(fn (string $label): string => __($label), Template::TYPES)),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => __('Draft'),
                        'published' => __('Published'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_default')->label(__('Default')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'edit' => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }

    private static function conditionSelectors(): array
    {
        return [
            Forms\Components\Select::make('conditions.item_id')
                ->label(fn (Get $get): string => static::specificItemLabel((string) $get('type')))
                ->options(fn (Get $get): array => static::specificItemOptions((string) $get('type')))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('conditions.type') === 'specific_item' && array_key_exists((string) $get('type'), static::specificItemTypeLabels())),
            Forms\Components\Select::make('conditions.category_id')
                ->label(fn (Get $get): string => static::categoryConditionLabel((string) $get('type')))
                ->options(fn (Get $get): array => static::categoryConditionOptions((string) $get('type')))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('conditions.type') === 'category' && array_key_exists((string) $get('type'), static::categoryConditionTypeLabels())),

            Forms\Components\Placeholder::make('condition_note')
                ->label(__('Matching'))
                ->content(__('If no conditional template matches, the default/all template for this type is used. If that does not exist, the original Blade fallback is used.'))
                ->columnSpanFull(),
        ];
    }

    public static function previewContextLabel(string $type): string
    {
        return match ($type) {
            'post_single' => __('Preview post'),
            'project_single' => __('Preview project'),
            'product_single' => __('Preview product'),
            'service_single' => __('Preview service'),
            'gallery_single' => __('Preview gallery'),
            'post_category' => __('Preview blog category'),
            'project_category' => __('Preview project category'),
            'product_category' => __('Preview product category'),
            'gallery_category' => __('Preview gallery category'),
            default => __('Preview context'),
        };
    }

    public static function previewContextOptions(string $type): array
    {
        return match ($type) {
            'post_single' => Post::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_single' => Project::query()->orderBy('title')->pluck('title', 'id')->all(),
            'product_single' => Product::query()->orderBy('title')->pluck('title', 'id')->all(),
            'service_single' => Service::query()->orderBy('name')->pluck('name', 'id')->all(),
            'gallery_single' => Gallery::query()->orderBy('title')->pluck('title', 'id')->all(),
            'post_category' => Category::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_category' => ProjectCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'product_category' => ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'gallery_category' => GalleryCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    private static function conditionSummaryFromState(array $conditions, bool $isDefault): string
    {
        $type = $conditions['type'] ?? 'all';

        if ($type === 'specific_item') {
            return __('Specific item #').($conditions['item_id'] ?? '-');
        }

        if ($type === 'category') {
            return __('Category #').($conditions['category_id'] ?? '-');
        }

        return $isDefault ? __('All / default') : __('All');
    }

    private static function canMatchSummary(string $type, array $conditions): string
    {
        if (blank($type)) {
            return __('Select a type first.');
        }

        $conditionType = $conditions['type'] ?? 'all';

        if ($conditionType === 'specific_item') {
            return filled($conditions['item_id'] ?? null) ? __('Yes, if that item exists.') : __('No, select a specific item.');
        }

        if ($conditionType === 'category') {
            return filled($conditions['category_id'] ?? null) ? __('Yes, if that category exists.') : __('No, select a category.');
        }

        return __('Yes, all/default templates can match this type.');
    }

    private static function debugWarnings(string $type, string $status, array $conditions, array $blocks): string
    {
        $warnings = [];

        if ($status !== 'published') {
            $warnings[] = __('Draft templates are ignored on public pages but can be previewed by admins.');
        }

        if (! static::conditionReferenceExists($type, $conditions)) {
            $warnings[] = __('The selected condition references a missing item/category or is incomplete.');
        }

        if (in_array($type, [
            'blog_index', 'post_single', 'post_category',
            'projects_index', 'project_single', 'project_category',
            'project_discovery_index',
            'shop_index', 'product_single', 'product_category',
            'service_index', 'service_single',
            'galleries_index', 'gallery_single', 'gallery_category',
        ], true) && ! static::usesDynamicBlocks($blocks)) {
            $warnings[] = __('This replacement template has no dynamic blocks, so current content may not appear.');
        }

        return $warnings ? implode(' ', $warnings) : __('No obvious issues.');
    }

    private static function conditionReferenceExists(string $type, array $conditions): bool
    {
        $conditionType = $conditions['type'] ?? 'all';

        if ($conditionType === 'all') {
            return true;
        }

        if ($conditionType === 'specific_item') {
            $id = (int) ($conditions['item_id'] ?? 0);

            return $id > 0 && array_key_exists($id, static::specificItemOptions($type));
        }

        if ($conditionType === 'category') {
            $id = (int) ($conditions['category_id'] ?? 0);

            return $id > 0 && array_key_exists($id, static::categoryConditionOptions($type));
        }

        return false;
    }

    private static function usesDynamicBlocks(array $blocks): bool
    {
        $types = collect($blocks)
            ->pluck('type')
            ->filter(fn (mixed $type): bool => is_string($type));

        if ($types->intersect([
            'template_archive_header',
            'template_content_grid',
            'template_shop_complete',
            'template_single_header',
            'template_single_content',
            'template_single_meta',
            'template_single_gallery',
            'template_add_to_cart',
        ])->isNotEmpty()) {
            return true;
        }

        $registry = app(BlockRegistry::class);

        return $types->contains(function (string $type) use ($registry): bool {
            return $registry->has($type)
                && in_array('dynamic_data', $registry->find($type)->capabilities(), true);
        });
    }

    private static function specificItemTypeLabels(): array
    {
        return [
            'post_single' => __('Post'),
            'post_category' => __('Blog category'),
            'project_single' => __('Project'),
            'project_category' => __('Project category'),
            'product_single' => __('Product'),
            'service_single' => __('Service'),
            'product_category' => __('Product category'),
            'gallery_single' => __('Gallery'),
            'gallery_category' => __('Gallery category'),
        ];
    }

    private static function categoryConditionTypeLabels(): array
    {
        return [
            'post_single' => __('Blog category'),
            'post_category' => __('Blog category'),
            'project_single' => __('Project category'),
            'project_category' => __('Project category'),
            'product_single' => __('Product category'),
            'product_category' => __('Product category'),
            'gallery_single' => __('Gallery category'),
            'gallery_category' => __('Gallery category'),
        ];
    }

    private static function specificItemLabel(string $type): string
    {
        return static::specificItemTypeLabels()[$type] ?? __('Specific item');
    }

    private static function categoryConditionLabel(string $type): string
    {
        return static::categoryConditionTypeLabels()[$type] ?? __('Category');
    }

    private static function specificItemOptions(string $type): array
    {
        return match ($type) {
            'post_single' => Post::query()->orderBy('title')->pluck('title', 'id')->all(),
            'post_category' => Category::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_single' => Project::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_category' => ProjectCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'product_single' => Product::query()->orderBy('title')->pluck('title', 'id')->all(),
            'service_single' => Service::query()->orderBy('name')->pluck('name', 'id')->all(),
            'product_category' => ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'gallery_single' => Gallery::query()->orderBy('title')->pluck('title', 'id')->all(),
            'gallery_category' => GalleryCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    private static function categoryConditionOptions(string $type): array
    {
        return match ($type) {
            'post_single', 'post_category' => Category::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_single', 'project_category' => ProjectCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'product_single', 'product_category' => ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'gallery_single', 'gallery_category' => GalleryCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    private static function blockDefinitions(?string $target = null, array $existingBlocks = []): array
    {
        $registry = app(BlockRegistry::class);
        $existingKeys = array_values(array_unique(array_filter(array_column($existingBlocks, 'type'), 'is_string')));
        $availableKeys = $registry->templateKeys($target);
        // Keep schemas for stored blocks, including ones incompatible with a changed target.
        $registeredKeys = array_values(array_filter(
            $registry->keys(),
            fn (string $key): bool => in_array($key, $availableKeys, true) || in_array($key, $existingKeys, true),
        ));

        $blocks = [
            ...$registry->filamentBlocks($registeredKeys, HeroBlock::CONTEXT_TEMPLATE),
            TemplateBlock::make('faq')
                ->label(__('Static: FAQ'))
                ->schema(static::sectionFields([
                    Forms\Components\TextInput::make('section_title')->label(__('Section title'))->required()->maxLength(255),
                    static::headingTagField(),
                    Forms\Components\Repeater::make('items')->label(__('Items'))
                        ->schema([
                            Forms\Components\TextInput::make('question')->label(__('Question'))->required()->maxLength(255),
                            Forms\Components\Textarea::make('answer')->label(__('Answer'))->required()->rows(3),
                        ])
                        ->columnSpanFull(),
                ])),
            TemplateBlock::make('gallery')
                ->label(__('Static: Gallery'))
                ->schema(static::sectionFields([
                    Forms\Components\TextInput::make('section_title')->label(__('Section title'))->required()->maxLength(255),
                    static::headingTagField(),
                    Forms\Components\Repeater::make('images')
                        ->schema([
                            Forms\Components\ViewField::make('url')
                                ->label(__('Image'))
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                                ->required(),
                            Forms\Components\TextInput::make('alt')->label(__('Alt'))->maxLength(255),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])),
            TemplateBlock::make('testimonials')
                ->label(__('Static: Testimonials'))
                ->schema(static::sectionFields([
                    Forms\Components\TextInput::make('section_title')->label(__('Section title'))->required()->maxLength(255),
                    static::headingTagField(),
                    Forms\Components\Repeater::make('items')->label(__('Items'))
                        ->schema([
                            Forms\Components\TextInput::make('name')->live(onBlur: true)->required()->maxLength(255),
                            Forms\Components\TextInput::make('role')->label(__('Role'))->maxLength(255),
                            Forms\Components\RichEditor::make('quote')->label(__('Quote'))->required()->columnSpanFull(),
                            Forms\Components\ViewField::make('avatar')->label(__('Avatar'))
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()]),
                        ])
                        ->columnSpanFull()
                        ->itemLabel(fn (array $state): string => filled($state['name'] ?? null) ? (string) $state['name'] : 'نظر جدید')
                        ->collapsible(),
                    Forms\Components\TextInput::make('cta_label')
                        ->label(__('Optional button label'))
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => filled($get('cta_action.type'))),
                    ActionPicker::make('cta_action')
                        ->label(__('Optional button destination'))
                        ->columnSpanFull(),
                ])),
            TemplateBlock::make('template_archive_header')
                ->forTemplateTargets(TemplateTargets::ARCHIVES)
                ->label(__('Dynamic: Archive Header'))
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\TextInput::make('eyebrow')
                        ->label(__('Optional eyebrow'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title')
                        ->label(__('Override title'))
                        ->helperText(__('Leave empty to use the current archive/category title.'))
                        ->maxLength(255),
                    static::headingTagField(default: 'h1'),
                    Forms\Components\Textarea::make('description')
                        ->label(__('Override description'))
                        ->helperText(__('Leave empty to use the current archive/category description.'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('variant')->label(__('Variant'))
                        ->options(['default' => __('Default'), 'modern' => __('Modern hero')])
                        ->default('default'),
                    Forms\Components\Select::make('alignment')->label(__('Alignment'))
                        ->options(['start' => __('Start'), 'center' => __('Center')])
                        ->default('start'),
                    Forms\Components\Select::make('spacing')->label(__('Spacing'))
                        ->options(['compact' => __('Compact'), 'comfortable' => __('Comfortable')])
                        ->default('comfortable'),
                    Forms\Components\Select::make('background_type')
                        ->label(__('Background type'))
                        ->options([
                            'default' => __('Default'),
                            'solid' => __('Solid color'),
                            'gradient' => __('Gradient'),
                            'image' => __('Image'),
                        ])
                        ->default('default')
                        ->live(),
                    Forms\Components\ColorPicker::make('background_color')->label(__('Background color'))
                        ->visible(fn (Get $get): bool => $get('background_type') === 'solid'),
                    Forms\Components\ColorPicker::make('gradient_from')->label(__('Gradient from'))
                        ->visible(fn (Get $get): bool => $get('background_type') === 'gradient'),
                    Forms\Components\ColorPicker::make('gradient_to')->label(__('Gradient to'))
                        ->visible(fn (Get $get): bool => $get('background_type') === 'gradient'),
                    Forms\Components\ViewField::make('background_image')
                        ->label(__('Background image'))
                        ->view('filament.forms.components.media-library-url-picker')
                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                        ->helperText(__('Choose from Media Library or paste an image URL.'))
                        ->visible(fn (Get $get): bool => $get('background_type') === 'image')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('overlay_opacity')
                        ->label(__('Overlay opacity'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(90)
                        ->default(45)
                        ->suffix('%')
                        ->helperText(__('Keep between 0 and 90 for readable text.'))
                        ->visible(fn (Get $get): bool => $get('background_type') === 'image'),
                ])
                ->columns(2),
            TemplateBlock::make('template_shop_complete')
                ->forTemplateTargets(['shop_index', 'product_category'])
                ->label(__('Dynamic: Complete Shop Page'))
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Forms\Components\TextInput::make('eyebrow')
                        ->label(__('Optional eyebrow'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title')
                        ->label(__('Override title'))
                        ->helperText(__('Leave empty to use the shop title.'))
                        ->maxLength(255),
                    static::headingTagField(default: 'h1'),
                    Forms\Components\Textarea::make('description')
                        ->label(__('Override description'))
                        ->helperText(__('Leave empty to use the shop description.'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('background_image'),
                    Forms\Components\ViewField::make('background_media_id')
                        ->label(__('Hero background image'))
                        ->view('filament.forms.components.media-library-picker')
                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                        ->afterStateHydrated(function (Forms\Components\ViewField $component, mixed $state, Get $get): void {
                            if (blank($state) && filled($get('background_image'))) {
                                $component->state(app(HeroMediaResolver::class)->resolveSourceId($get('background_image')));
                            }
                        })
                        ->helperText(__('Choose a reusable image from Media Library.'))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('overlay_opacity')
                        ->label(__('Overlay opacity'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(90)
                        ->default(20)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('search_placeholder')
                        ->label(__('Search placeholder'))
                        ->default('Search products')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('category_label')
                        ->label(__('Category dropdown label'))
                        ->default('Categories')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('category_section_title')
                        ->label(__('Category section title'))
                        ->default('Shop by category')
                        ->maxLength(255),
                    static::headingTagField(__('Category heading tag'), 'category_heading_tag'),
                    Forms\Components\Hidden::make('all_categories_image'),
                    Forms\Components\ViewField::make('all_categories_media_id')
                        ->label(__('All products category image'))
                        ->view('filament.forms.components.media-library-picker')
                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                        ->afterStateHydrated(function (Forms\Components\ViewField $component, mixed $state, Get $get): void {
                            if (blank($state) && filled($get('all_categories_image'))) {
                                $component->state(app(HeroMediaResolver::class)->resolveSourceId($get('all_categories_image')));
                            }
                        })
                        ->helperText(__('Optional image for the "All products" card in the category slider.'))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('products_title')
                        ->label(__('Products section title'))
                        ->default('Products')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('empty_message')
                        ->label(__('Empty message'))
                        ->default('No products matched your filters.')
                        ->maxLength(255),
                    Forms\Components\Placeholder::make('context_note')
                        ->label(__('Context'))
                        ->content(__('Designed for Shop index templates. It renders the current product loop, category cards, search, and filters.'))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            TemplateBlock::make('template_content_grid')
                ->forTemplateTargets(TemplateTargets::COLLECTIONS)
                ->label(__('Dynamic: Content Grid'))
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('Optional section title'))
                        ->maxLength(255),
                    static::headingTagField(),
                    Forms\Components\TextInput::make('empty_message')
                        ->label(__('Empty message'))
                        ->maxLength(255),
                    Forms\Components\Select::make('columns_desktop')
                        ->label(__('Desktop columns'))
                        ->options([2 => '2', 3 => '3', 4 => '4'])
                        ->default(3),
                    Forms\Components\Select::make('columns_tablet')
                        ->label(__('Tablet columns'))
                        ->options([1 => '1', 2 => '2'])
                        ->default(2),
                    Forms\Components\Select::make('image_ratio')->label(__('Image ratio'))
                        ->options(['16:10' => '16:10', '16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1'])
                        ->default('16:10'),
                    Forms\Components\Select::make('card_density')->label(__('Card density'))
                        ->options(['compact' => __('Compact'), 'comfortable' => __('Comfortable')])
                        ->default('comfortable'),
                    Forms\Components\Select::make('presentation_variant')
                        ->label(__('Presentation'))
                        ->options([
                            'clean_grid' => __('Classic cards'),
                            'masonry_gallery' => __('Masonry gallery'),
                        ])
                        ->helperText(__('Masonry is image-first and reveals card information on hover or keyboard focus.')),
                    Forms\Components\Toggle::make('show_image')->label(__('Show image'))->default(true),
                    Forms\Components\Toggle::make('show_icon')->label(__('Show icon'))->default(true),
                    Forms\Components\Toggle::make('show_excerpt')->label(__('Show excerpt'))->default(true),
                    Forms\Components\Toggle::make('show_badges')->label(__('Show badges'))->default(true),
                    Forms\Components\Toggle::make('show_meta')->label(__('Show meta'))->default(true),
                    Forms\Components\Toggle::make('show_action')->label(__('Show action'))->default(true),
                    Forms\Components\TextInput::make('action_label')
                        ->label(__('Card action label'))
                        ->maxLength(120),
                    Forms\Components\Placeholder::make('context_note')
                        ->label(__('Context'))
                        ->content(__('Renders the canonical archive collection. Visibility settings only affect presentation; domain data and pagination remain unchanged.'))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            TemplateBlock::make('template_single_header')
                ->forTemplateTargets(TemplateTargets::GENERIC_SINGLE)
                ->label(__('Dynamic: Single Header'))
                ->icon('heroicon-o-identification')
                ->schema([
                    Forms\Components\TextInput::make('eyebrow')
                        ->label(__('Optional eyebrow'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title')
                        ->label(__('Override title'))
                        ->helperText(__('Leave empty to use the current item title.'))
                        ->maxLength(255),
                    static::headingTagField(default: 'h1'),
                    Forms\Components\Textarea::make('description')
                        ->label(__('Override excerpt'))
                        ->helperText(__('Leave empty to use the current item excerpt.'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            TemplateBlock::make('template_single_content')
                ->forTemplateTargets(TemplateTargets::GENERIC_SINGLE)
                ->label(__('Dynamic: Single Content'))
                ->icon('heroicon-o-document')
                ->schema([
                    Forms\Components\Placeholder::make('context_note')
                        ->label(__('Context'))
                        ->content(__('Renders the main content/body of the current post, product, project, or gallery.')),
                ]),
            TemplateBlock::make('template_single_meta')
                ->forTemplateTargets(TemplateTargets::GENERIC_SINGLE)
                ->label(__('Dynamic: Single Meta'))
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Forms\Components\Placeholder::make('context_note')
                        ->label(__('Context'))
                        ->content(__('Renders useful metadata based on the current item type: product price/SKU/stock, project client/location/date/services, post category/date, or gallery type/category/project.')),
                ]),
            TemplateBlock::make('template_single_gallery')
                ->forTemplateTargets(['project_single', 'product_single', 'gallery_single'])
                ->label(__('Dynamic: Single Gallery'))
                ->icon('heroicon-o-photo')
                ->schema([
                    Forms\Components\TextInput::make('title')->label(__('Title'))
                        ->default('Gallery')
                        ->maxLength(255),
                    static::headingTagField(),
                    Forms\Components\TextInput::make('video_title')->label(__('Video title'))
                        ->default('Video')
                        ->maxLength(255),
                    static::headingTagField(__('Video heading tag'), 'video_heading_tag'),
                ])
                ->columns(2),
            TemplateBlock::make('template_add_to_cart')
                ->forTemplateTargets(['product_single'])
                ->label(__('Dynamic: Add To Cart'))
                ->icon('heroicon-o-shopping-cart')
                ->schema([
                    Forms\Components\TextInput::make('button_label')->label(__('Button label'))
                        ->default('Add to cart')
                        ->maxLength(255),
                    Forms\Components\Placeholder::make('context_note')
                        ->label(__('Context'))
                        ->content(__('Only renders on product single templates. It is hidden safely in other contexts.'))
                        ->columnSpanFull(),
                ]),
            TemplateBlock::make('custom_html')
                ->label(__('Trusted: Custom HTML / CSS / JS'))
                ->icon('heroicon-o-code-bracket-square')
                ->schema([
                    Forms\Components\Textarea::make('code')
                        ->label(__('Code'))
                        ->rows(18)
                        ->required()
                        ->helperText(__('Trusted admins only. This code is rendered raw and can include HTML, CSS, and JavaScript.'))
                        ->columnSpanFull(),
                ]),
        ];

        $available = $registry->filterForTemplate($blocks, $target);

        return array_values(array_filter(
            $blocks,
            fn (Forms\Components\Builder\Block $block): bool => in_array($block, $available, true)
                || in_array($block->getName(), $existingKeys, true),
        ));
    }

    private static function sectionFields(array $fields): array
    {
        return [
            Forms\Components\Select::make('section_background')
                ->label(__('Section background'))
                ->options(['default' => __('Default'), 'muted' => __('Muted'), 'dark' => __('Dark')])
                ->default('default'),
            Forms\Components\Select::make('alignment')->label(__('Alignment'))
                ->options(['left' => __('Left'), 'center' => __('Center')])
                ->default('center'),
            Forms\Components\TextInput::make('eyebrow')
                ->label(__('Eyebrow'))
                ->maxLength(255),
            ...$fields,
        ];
    }

    private static function headingTagField(
        string $label = 'Heading tag',
        string $name = 'heading_tag',
        string $default = 'h2',
    ): Forms\Components\Select {
        return HeadingLevel::field($name, __($label), $default);
    }
}
