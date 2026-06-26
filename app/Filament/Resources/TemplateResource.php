<?php

namespace App\Filament\Resources;

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

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Template settings')
                ->description('Published default templates replace the built-in layout for their selected type. If no published template exists, the original Blade view is used as fallback.')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('slug'))
                            ? $set('slug', Str::slug($state ?? ''))
                            : null),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('type')
                        ->required()
                        ->options(Template::TYPES)
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('conditions.item_id', null);
                            $set('conditions.category_id', null);
                        }),
                    Forms\Components\Select::make('status')
                        ->required()
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ])
                        ->default('draft'),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default for this type')
                        ->default(true)
                        ->helperText('Only published default templates are used by public dynamic pages.'),
                    Forms\Components\TextInput::make('priority')
                        ->numeric()
                        ->default(0)
                        ->helperText('Higher priority wins when more than one default template exists.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Conditions')
                ->description('Specific item templates override category/all templates. Category templates apply to items inside that category. Priority resolves conflicts inside the same specificity level. Draft templates are ignored.')
                ->schema([
                    Forms\Components\Select::make('conditions.type')
                        ->label('Condition type')
                        ->options(Template::CONDITION_TYPES)
                        ->default('all')
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('conditions.item_id', null);
                            $set('conditions.category_id', null);
                        })
                        ->helperText('Index, header, and footer templates normally use All.'),

                    ...static::conditionSelectors(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Debug')
                ->description('Read-only matching hints for this template.')
                ->schema([
                    Forms\Components\Placeholder::make('debug_type')
                        ->label('Template type')
                        ->content(fn (Get $get): string => Template::TYPES[$get('type')] ?? ($get('type') ?: 'Not selected')),
                    Forms\Components\Placeholder::make('debug_status')
                        ->label('Status')
                        ->content(fn (Get $get): string => (string) ($get('status') ?: 'draft')),
                    Forms\Components\Placeholder::make('debug_condition')
                        ->label('Condition')
                        ->content(fn (Get $get): string => static::conditionSummaryFromState($get('conditions') ?? [], (bool) $get('is_default'))),
                    Forms\Components\Placeholder::make('debug_priority')
                        ->label('Priority')
                        ->content(fn (Get $get): string => (string) ($get('priority') ?? 0)),
                    Forms\Components\Placeholder::make('debug_default')
                        ->label('Default')
                        ->content(fn (Get $get): string => $get('is_default') ? 'Yes' : 'No'),
                    Forms\Components\Placeholder::make('debug_match')
                        ->label('Can match')
                        ->content(fn (Get $get): string => static::canMatchSummary((string) $get('type'), $get('conditions') ?? [])),
                    Forms\Components\Placeholder::make('debug_warnings')
                        ->label('Warnings')
                        ->content(fn (Get $get): string => static::debugWarnings(
                            (string) $get('type'),
                            (string) $get('status'),
                            $get('conditions') ?? [],
                            $get('blocks') ?? [],
                        ))
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('debug_specificity')
                        ->label('Specificity')
                        ->content('specific item > category > all/default. Priority only resolves conflicts inside the same specificity level.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Blocks')
                ->description('Use static blocks for fixed sections and dynamic template blocks to render the current post, product, project, gallery, category, or archive collection. Custom Code blocks should be used only by trusted admins.')
                ->schema([
                    Forms\Components\Builder::make('blocks')
                        ->label('Template blocks')
                        ->blocks(static::blockDefinitions())
                        ->collapsible()
                        ->reorderable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => Template::TYPES[$state] ?? $state)->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('condition_summary')
                    ->label('Condition')
                    ->state(fn (Template $record): string => $record->conditionSummary())
                    ->badge(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default')->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(Template::TYPES),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
                Tables\Filters\TernaryFilter::make('is_default')->label('Default'),
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
                ->label('Matching')
                ->content('If no conditional template matches, the default/all template for this type is used. If that does not exist, the original Blade fallback is used.')
                ->columnSpanFull(),
        ];
    }

    public static function previewContextLabel(string $type): string
    {
        return match ($type) {
            'post_single' => 'Preview post',
            'project_single' => 'Preview project',
            'product_single' => 'Preview product',
            'gallery_single' => 'Preview gallery',
            'post_category' => 'Preview blog category',
            'project_category' => 'Preview project category',
            'product_category' => 'Preview product category',
            'gallery_category' => 'Preview gallery category',
            default => 'Preview context',
        };
    }

    public static function previewContextOptions(string $type): array
    {
        return match ($type) {
            'post_single' => Post::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_single' => Project::query()->orderBy('title')->pluck('title', 'id')->all(),
            'product_single' => Product::query()->orderBy('title')->pluck('title', 'id')->all(),
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
            return 'Specific item #'.($conditions['item_id'] ?? '-');
        }

        if ($type === 'category') {
            return 'Category #'.($conditions['category_id'] ?? '-');
        }

        return $isDefault ? 'All / default' : 'All';
    }

    private static function canMatchSummary(string $type, array $conditions): string
    {
        if (blank($type)) {
            return 'Select a type first.';
        }

        $conditionType = $conditions['type'] ?? 'all';

        if ($conditionType === 'specific_item') {
            return filled($conditions['item_id'] ?? null) ? 'Yes, if that item exists.' : 'No, select a specific item.';
        }

        if ($conditionType === 'category') {
            return filled($conditions['category_id'] ?? null) ? 'Yes, if that category exists.' : 'No, select a category.';
        }

        return 'Yes, all/default templates can match this type.';
    }

    private static function debugWarnings(string $type, string $status, array $conditions, array $blocks): string
    {
        $warnings = [];

        if ($status !== 'published') {
            $warnings[] = 'Draft templates are ignored on public pages but can be previewed by admins.';
        }

        if (! static::conditionReferenceExists($type, $conditions)) {
            $warnings[] = 'The selected condition references a missing item/category or is incomplete.';
        }

        if (in_array($type, [
            'blog_index', 'post_single', 'post_category',
            'projects_index', 'project_single', 'project_category',
            'shop_index', 'product_single', 'product_category',
            'galleries_index', 'gallery_single', 'gallery_category',
        ], true) && ! static::usesDynamicBlocks($blocks)) {
            $warnings[] = 'This replacement template has no dynamic blocks, so current content may not appear.';
        }

        return $warnings ? implode(' ', $warnings) : 'No obvious issues.';
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
        return collect($blocks)
            ->pluck('type')
            ->intersect([
                'template_archive_header',
                'template_content_grid',
                'template_shop_complete',
                'template_single_header',
                'template_single_content',
                'template_single_meta',
                'template_single_gallery',
                'template_add_to_cart',
            ])
            ->isNotEmpty();
    }

    private static function specificItemTypeLabels(): array
    {
        return [
            'post_single' => 'Post',
            'post_category' => 'Blog category',
            'project_single' => 'Project',
            'project_category' => 'Project category',
            'product_single' => 'Product',
            'product_category' => 'Product category',
            'gallery_single' => 'Gallery',
            'gallery_category' => 'Gallery category',
        ];
    }

    private static function categoryConditionTypeLabels(): array
    {
        return [
            'post_single' => 'Blog category',
            'post_category' => 'Blog category',
            'project_single' => 'Project category',
            'project_category' => 'Project category',
            'product_single' => 'Product category',
            'product_category' => 'Product category',
            'gallery_single' => 'Gallery category',
            'gallery_category' => 'Gallery category',
        ];
    }

    private static function specificItemLabel(string $type): string
    {
        return static::specificItemTypeLabels()[$type] ?? 'Specific item';
    }

    private static function categoryConditionLabel(string $type): string
    {
        return static::categoryConditionTypeLabels()[$type] ?? 'Category';
    }

    private static function specificItemOptions(string $type): array
    {
        return match ($type) {
            'post_single' => Post::query()->orderBy('title')->pluck('title', 'id')->all(),
            'post_category' => Category::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_single' => Project::query()->orderBy('title')->pluck('title', 'id')->all(),
            'project_category' => ProjectCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'product_single' => Product::query()->orderBy('title')->pluck('title', 'id')->all(),
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

    private static function blockDefinitions(): array
    {
        return [
            Forms\Components\Builder\Block::make('hero')
                ->label('Static: Hero')
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    Forms\Components\Select::make('template')
                        ->label('Template')
                        ->options([
                            'default' => 'Default',
                            'hero_1' => 'Hero 1 - full background image',
                            'hero_2' => 'Hero 2 - selector CTA',
                            'hero_3' => 'Hero 3 - split image and stats',
                        ])
                        ->default('default')
                        ->live(),
                    Forms\Components\TextInput::make('eyebrow')->maxLength(255),
                    static::iconsaxIconPicker('hero_1_eyebrow_icon', 'Eyebrow icon')
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
                    static::iconsaxIconSizeInput('hero_1_eyebrow_icon_size', 'Size')
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
                    Forms\Components\Select::make('hero_1_theme')
                        ->label('Hero 1 appearance')
                        ->options([
                            'image' => 'Dark image',
                            'light_grid' => 'Light grid',
                        ])
                        ->default('image')
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
                    Forms\Components\TextInput::make('title')->required()->maxLength(255),
                    Forms\Components\TextInput::make('hero_1_title_second_line')
                        ->label('Hero 1 title second line')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
                    Forms\Components\Toggle::make('hero_1_show_underline')
                        ->label('Show Hero 1 underline')
                        ->default(false)
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
                    Forms\Components\TextInput::make('subtitle')->maxLength(255),
                    Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                    Forms\Components\TextInput::make('primary_button_label')->maxLength(255),
                    Forms\Components\TextInput::make('primary_button_url')->maxLength(255),
                    Forms\Components\TextInput::make('secondary_button_label')->maxLength(255),
                    Forms\Components\TextInput::make('secondary_button_url')->maxLength(255),
                    Forms\Components\Select::make('hero_2_alignment')->options(['left' => 'Left', 'right' => 'Right'])->default('left')->visible(fn (Get $get): bool => $get('template') === 'hero_2'),
                    Forms\Components\TextInput::make('hero_2_height')
                        ->label('Block height')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('px')
                        ->placeholder('Example: 560')
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_2')
                        ->columnSpan(1),
                    Forms\Components\Section::make('Hero 2 background')
                        ->schema([
                            Forms\Components\Select::make('hero_2_background_type')
                                ->label('Background type')
                                ->options([
                                    'image' => 'Image',
                                    'video' => 'Video',
                                ])
                                ->default('image')
                                ->afterStateHydrated(function (Forms\Components\Select $component, ?string $state, Get $get): void {
                                    if (blank($state) && filled($get('hero_2_video_url'))) {
                                        $component->state('video');
                                    }
                                })
                                ->live(),
                            Forms\Components\ViewField::make('image')
                                ->label('Background image')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                                ->visible(fn (Get $get): bool => ($get('hero_2_background_type') ?: (filled($get('hero_2_video_url')) ? 'video' : 'image')) === 'image')
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('hero_2_video_url')
                                ->label('Background video')
                                ->view('filament.forms.components.media-library-video-url-picker')
                                ->viewData(fn (): array => ['videos' => static::mediaLibraryVideoItems()])
                                ->helperText('The video starts after the full page finishes loading.')
                                ->visible(fn (Get $get): bool => ($get('hero_2_background_type') ?: (filled($get('hero_2_video_url')) ? 'video' : 'image')) === 'video')
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('hero_2_video_poster')
                                ->label('Video thumbnail')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                                ->helperText('Shown before the full page finishes loading and the video starts.')
                                ->visible(fn (Get $get): bool => ($get('hero_2_background_type') ?: (filled($get('hero_2_video_url')) ? 'video' : 'image')) === 'video')
                                ->columnSpanFull(),
                        ])
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_2')
                        ->columns(2)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('hero_3_alignment')->options(['left' => 'Left', 'right' => 'Right'])->default('right')->visible(fn (Get $get): bool => $get('template') === 'hero_3'),
                    Forms\Components\Repeater::make('selector_items')
                        ->schema([
                            Forms\Components\TextInput::make('label')->required()->maxLength(255),
                            Forms\Components\TextInput::make('url')->required()->maxLength(255),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_2'),
                    Forms\Components\Repeater::make('stats')
                        ->schema([
                            Forms\Components\TextInput::make('value')->required()->maxLength(80),
                            Forms\Components\TextInput::make('label')->required()->maxLength(120),
                            Forms\Components\TextInput::make('description')->maxLength(160),
                            static::iconsaxIconPicker('icon', 'Icon'),
                            static::iconsaxIconSizeInput(label: 'Size'),
                        ])
                        ->columns(5)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_3'),
                    Forms\Components\Repeater::make('hero_1_social_links')
                        ->label('Hero 1 bottom links')
                        ->schema([
                            Forms\Components\TextInput::make('label')->required()->maxLength(120),
                            Forms\Components\TextInput::make('url')->required()->maxLength(255),
                            static::iconsaxIconPicker('icon', 'Icon'),
                            static::iconsaxIconSizeInput(label: 'Size'),
                        ])
                        ->columns(4)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
                    Forms\Components\TextInput::make('hero_1_scroll_label')
                        ->label('Hero 1 scroll label')
                        ->maxLength(120)
                        ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
                    Forms\Components\ViewField::make('image')
                        ->label('Image')
                        ->view('filament.forms.components.media-library-url-picker')
                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                        ->visible(fn (Get $get): bool => $get('template') !== 'hero_2')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Builder\Block::make('cta')
                ->label('Static: CTA')
                ->icon('heroicon-o-megaphone')
                ->schema(static::ctaFields())
                ->columns(2),
            Forms\Components\Builder\Block::make('feature_grid')
                ->label('Static: Feature Grid')
                ->schema(static::sectionFields([
                    Forms\Components\TextInput::make('section_title')->required()->maxLength(255),
                    Forms\Components\Textarea::make('section_description')->rows(3)->columnSpanFull(),
                    Forms\Components\Repeater::make('items')
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Item')
                        ->schema([
                            Forms\Components\TextInput::make('title')->required()->maxLength(255),
                            static::iconsaxIconPicker('icon', 'Icon'),
                            static::iconsaxIconSizeInput(label: 'Size'),
                            Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->collapsible()
                        ->collapsed(),
                ])),
            Forms\Components\Builder\Block::make('faq')
                ->label('Static: FAQ')
                ->schema(static::sectionFields([
                    Forms\Components\TextInput::make('section_title')->required()->maxLength(255),
                    Forms\Components\Repeater::make('items')
                        ->schema([
                            Forms\Components\TextInput::make('question')->required()->maxLength(255),
                            Forms\Components\Textarea::make('answer')->required()->rows(3),
                        ])
                        ->columnSpanFull(),
                ])),
            Forms\Components\Builder\Block::make('gallery')
                ->label('Static: Gallery')
                ->schema(static::sectionFields([
                    Forms\Components\TextInput::make('section_title')->required()->maxLength(255),
                    Forms\Components\Repeater::make('images')
                        ->schema([
                            Forms\Components\ViewField::make('url')
                                ->label('Image')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                                ->required(),
                            Forms\Components\TextInput::make('alt')->maxLength(255),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])),
            Forms\Components\Builder\Block::make('testimonials')
                ->label('Static: Testimonials')
                ->schema(static::sectionFields([
                    Forms\Components\TextInput::make('section_title')->required()->maxLength(255),
                    Forms\Components\Repeater::make('items')
                        ->schema([
                            Forms\Components\TextInput::make('name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('role')->maxLength(255),
                            Forms\Components\Textarea::make('quote')->required()->rows(3),
                            Forms\Components\ViewField::make('avatar')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()]),
                        ])
                        ->columnSpanFull(),
                ])),
            Forms\Components\Builder\Block::make('template_archive_header')
                ->label('Dynamic: Archive Header')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\TextInput::make('eyebrow')
                        ->label('Optional eyebrow')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title')
                        ->label('Override title')
                        ->helperText('Leave empty to use the current archive/category title.')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Override description')
                        ->helperText('Leave empty to use the current archive/category description.')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\ViewField::make('background_image')
                        ->label('Background image')
                        ->view('filament.forms.components.media-library-url-picker')
                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                        ->helperText('Choose from Media Library or paste an image URL.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('overlay_opacity')
                        ->label('Overlay opacity')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(90)
                        ->default(45)
                        ->suffix('%')
                        ->helperText('Keep between 0 and 90 for readable text.'),
                ])
                ->columns(2),
            Forms\Components\Builder\Block::make('template_shop_complete')
                ->label('Dynamic: Complete Shop Page')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Forms\Components\TextInput::make('eyebrow')
                        ->label('Optional eyebrow')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title')
                        ->label('Override title')
                        ->helperText('Leave empty to use the shop title.')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Override description')
                        ->helperText('Leave empty to use the shop description.')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\ViewField::make('background_image')
                        ->label('Hero background image')
                        ->view('filament.forms.components.media-library-url-picker')
                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                        ->helperText('Choose from Media Library or paste an image URL.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('overlay_opacity')
                        ->label('Overlay opacity')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(90)
                        ->default(20)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('search_placeholder')
                        ->label('Search placeholder')
                        ->default('Search products')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('category_label')
                        ->label('Category dropdown label')
                        ->default('Categories')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('category_section_title')
                        ->label('Category section title')
                        ->default('Shop by category')
                        ->maxLength(255),
                    Forms\Components\ViewField::make('all_categories_image')
                        ->label('All products category image')
                        ->view('filament.forms.components.media-library-url-picker')
                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                        ->helperText('Optional image for the "All products" card in the category slider.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('products_title')
                        ->label('Products section title')
                        ->default('Products')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('empty_message')
                        ->label('Empty message')
                        ->default('No products matched your filters.')
                        ->maxLength(255),
                    Forms\Components\Placeholder::make('context_note')
                        ->label('Context')
                        ->content('Designed for Shop index templates. It renders the current product loop, category cards, search, and filters.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Builder\Block::make('template_content_grid')
                ->label('Dynamic: Content Grid')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Optional section title')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('empty_message')
                        ->label('Empty message')
                        ->maxLength(255),
                    Forms\Components\Placeholder::make('context_note')
                        ->label('Context')
                        ->content('Renders the current archive collection: posts, projects, products, or galleries. Pagination is preserved when available.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Builder\Block::make('template_single_header')
                ->label('Dynamic: Single Header')
                ->icon('heroicon-o-identification')
                ->schema([
                    Forms\Components\TextInput::make('eyebrow')
                        ->label('Optional eyebrow')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title')
                        ->label('Override title')
                        ->helperText('Leave empty to use the current item title.')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Override excerpt')
                        ->helperText('Leave empty to use the current item excerpt.')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Builder\Block::make('template_single_content')
                ->label('Dynamic: Single Content')
                ->icon('heroicon-o-document')
                ->schema([
                    Forms\Components\Placeholder::make('context_note')
                        ->label('Context')
                        ->content('Renders the main content/body of the current post, product, project, or gallery.'),
                ]),
            Forms\Components\Builder\Block::make('template_single_meta')
                ->label('Dynamic: Single Meta')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Forms\Components\Placeholder::make('context_note')
                        ->label('Context')
                        ->content('Renders useful metadata based on the current item type: product price/SKU/stock, project client/location/date/services, post category/date, or gallery type/category/project.'),
                ]),
            Forms\Components\Builder\Block::make('template_single_gallery')
                ->label('Dynamic: Single Gallery')
                ->icon('heroicon-o-photo')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->default('Gallery')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('video_title')
                        ->default('Video')
                        ->maxLength(255),
                ])
                ->columns(2),
            Forms\Components\Builder\Block::make('template_add_to_cart')
                ->label('Dynamic: Add To Cart')
                ->icon('heroicon-o-shopping-cart')
                ->schema([
                    Forms\Components\TextInput::make('button_label')
                        ->default('Add to cart')
                        ->maxLength(255),
                    Forms\Components\Placeholder::make('context_note')
                        ->label('Context')
                        ->content('Only renders on product single templates. It is hidden safely in other contexts.')
                        ->columnSpanFull(),
                ]),
            Forms\Components\Builder\Block::make('custom_html')
                ->label('Trusted: Custom HTML / CSS / JS')
                ->icon('heroicon-o-code-bracket-square')
                ->schema([
                    Forms\Components\Textarea::make('code')
                        ->label('Code')
                        ->rows(18)
                        ->required()
                        ->helperText('Trusted admins only. This code is rendered raw and can include HTML, CSS, and JavaScript.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    private static function sectionFields(array $fields): array
    {
        return [
            Forms\Components\Select::make('section_background')
                ->label('Section background')
                ->options(['default' => 'Default', 'muted' => 'Muted', 'dark' => 'Dark'])
                ->default('default'),
            Forms\Components\Select::make('alignment')
                ->options(['left' => 'Left', 'center' => 'Center'])
                ->default('center'),
            Forms\Components\TextInput::make('eyebrow')
                ->label('Eyebrow')
                ->maxLength(255),
            ...$fields,
        ];
    }

    private static function ctaFields(): array
    {
        return [
            Forms\Components\Select::make('cta_template')
                ->label('CTA template')
                ->options([
                    'classic' => 'قالب فعلی',
                    'image' => 'قالب تصویری',
                ])
                ->default('classic')
                ->live()
                ->required(),
            Forms\Components\TextInput::make('content_width')
                ->label('Content width')
                ->numeric()
                ->minValue(240)
                ->maxValue(1400)
                ->default(580)
                ->suffix('px')
                ->helperText('Text content width for the image CTA template.')
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image'),
            Forms\Components\Select::make('section_background')
                ->label('Section background')
                ->options(['default' => 'Default', 'muted' => 'Muted', 'dark' => 'Dark'])
                ->default('default')
                ->visible(fn (Get $get): bool => ($get('cta_template') ?? 'classic') === 'classic'),
            Forms\Components\Select::make('alignment')
                ->options(['left' => 'Left', 'center' => 'Center'])
                ->default('center')
                ->visible(fn (Get $get): bool => ($get('cta_template') ?? 'classic') === 'classic'),
            Forms\Components\TextInput::make('eyebrow')
                ->label('Eyebrow')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => ($get('cta_template') ?? 'classic') === 'classic'),
            Forms\Components\ViewField::make('background_image')
                ->label('Background image')
                ->view('filament.forms.components.media-library-url-picker')
                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                ->helperText('Image CTA template only.')
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('button_label')
                ->label('Primary button label')
                ->maxLength(255),
            Forms\Components\TextInput::make('button_url')
                ->label('Primary button URL')
                ->maxLength(255),
            Forms\Components\TextInput::make('secondary_button_label')
                ->label('Secondary button label')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image'),
            Forms\Components\TextInput::make('secondary_button_url')
                ->label('Secondary button URL')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image'),
        ];
    }
}
