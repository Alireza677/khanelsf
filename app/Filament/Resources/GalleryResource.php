<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\GalleryResource\Pages;
use App\Models\Gallery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GalleryResource extends Resource
{
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = Gallery::class;

    protected static ?string $navigationGroup = 'Galleries';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Gallery editor')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Content')
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
                            Forms\Components\Select::make('gallery_category_id')
                                ->label('Category')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->helperText('Optional. Inactive categories are not shown publicly.'),
                            Forms\Components\Select::make('project_id')
                                ->label('Related project')
                                ->relationship('project', 'title')
                                ->searchable()
                                ->preload()
                                ->helperText('Optional. Related published galleries can appear on the project detail page.'),
                            Forms\Components\Textarea::make('excerpt')
                                ->rows(3)
                                ->helperText('Short summary used on gallery cards and as an SEO fallback.')
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('content')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('Media')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->required()
                                ->label('Gallery type')
                                ->options([
                                    'image' => 'Image',
                                    'video' => 'Video',
                                    'mixed' => 'Mixed',
                                ])
                                ->default('image')
                                ->live()
                                ->helperText('Use Video or Mixed when video_url should be shown on the public gallery page.'),
                            Forms\Components\ViewField::make('featured_media_id')
                                ->label('Featured image')
                                ->view('filament.forms.components.media-library-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Gallery $record): void {
                                    $set(
                                        'featured_media_id',
                                        $record?->featuredImage()?->getCustomProperty('source_media_id')
                                            ?: ($record?->featuredImage() ? '__keep_existing__' : null),
                                    );
                                })
                                ->helperText('Choose an existing image from Media Library. Upload new images from Media > Upload Media first.')
                                ->columnSpanFull(),
                            Forms\Components\SpatieMediaLibraryFileUpload::make('images')
                                ->collection('images')
                                ->label('Images')
                                ->multiple()
                                ->image()
                                ->imageEditor()
                                ->reorderable()
                                ->helperText('Upload image gallery items. Video files are intentionally not handled in this phase.')
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('video_url')
                                ->label('Video URL')
                                ->url()
                                ->maxLength(255)
                                ->helperText('Supports safe embeds for YouTube, Vimeo, and Aparat when the URL can be parsed. Otherwise a clean external link is shown. No upload or transcoding is performed.'),
                            Forms\Components\ViewField::make('thumbnail_url')
                                ->label('Video thumbnail URL')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->helperText('Optional preview image for video cards. Falls back to the featured image or first gallery image.'),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('SEO')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('SEO title')
                                ->maxLength(70)
                                ->helperText('Recommended: up to 70 characters. Falls back to the gallery title when empty.'),
                            Forms\Components\Textarea::make('seo_description')
                                ->maxLength(160)
                                ->rows(3)
                                ->helperText('Recommended: up to 160 characters. Falls back to excerpt, content, or site defaults.')
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('seo_image')
                                ->label('Social image URL')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->helperText('Falls back to thumbnail or featured image when empty.')
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('robots_index')
                                ->label('Allow search engines to index this gallery')
                                ->default(true),
                            Forms\Components\Toggle::make('robots_follow')
                                ->label('Allow search engines to follow links')
                                ->default(true),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('Publishing')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->required()
                                ->options([
                                    'draft' => 'Draft',
                                    'published' => 'Published',
                                    'archived' => 'Archived',
                                ])
                                ->default('draft'),
                            Forms\Components\DateTimePicker::make('published_at')->jalali()
                                ->seconds(false)
                                ->helperText('Leave empty to publish immediately when status is Published.'),
                            Forms\Components\Toggle::make('is_featured')
                                ->label('Featured gallery')
                                ->default(false),
                            Forms\Components\TextInput::make('sort_order')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ])
                        ->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('featured_image')
                    ->collection('featured_image')
                    ->conversion('thumb')
                    ->label('Image'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'video' => 'danger',
                        'mixed' => 'warning',
                        default => 'info',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Project')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'archived' => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured')
                    ->sortable(),
                Tables\Columns\IconColumn::make('robots_index')
                    ->boolean()
                    ->label('Index'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'image' => 'Image',
                        'video' => 'Video',
                        'mixed' => 'Mixed',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('project')
                    ->relationship('project', 'title'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Gallery $record): string => route('admin.preview.galleries.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('viewPublic')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Gallery $record): string => static::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Gallery $record): bool => static::isPubliclyVisible($record)),
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
            'index' => Pages\ListGalleries::route('/'),
            'create' => Pages\CreateGallery::route('/create'),
            'edit' => Pages\EditGallery::route('/{record}/edit'),
        ];
    }

    public static function publicUrl(Gallery $gallery): string
    {
        return route('galleries.show', $gallery->slug);
    }

    public static function isPubliclyVisible(Gallery $gallery): bool
    {
        return $gallery->status === 'published'
            && (blank($gallery->published_at) || $gallery->published_at->lte(now()));
    }
}
