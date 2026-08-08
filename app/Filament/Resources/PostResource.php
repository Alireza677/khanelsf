<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = Post::class;

    protected static ?string $navigationGroup = 'Blog';

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Post editor')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Content')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('slug'))
                                    ? $set('slug', static::slugFromTranslatedTitle($state))
                                    : null),
                            Forms\Components\TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->relationship('category', 'title')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Textarea::make('excerpt')
                                ->rows(3)
                                ->helperText('Short summary used on blog cards and as an SEO fallback.')
                                ->columnSpanFull(),
                            Forms\Components\View::make('filament.forms.components.media-library-content-inserter')
                                ->viewData(fn (): array => [
                                    'contentStatePath' => 'data.content',
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('content')
                                ->extraAttributes(['class' => 'post-content-scroll-editor'])
                                ->toolbarButtons([
                                    'blockquote',
                                    'bold',
                                    'bulletList',
                                    'codeBlock',
                                    'h2',
                                    'h3',
                                    'italic',
                                    'link',
                                    'orderedList',
                                    'redo',
                                    'strike',
                                    'underline',
                                    'undo',
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('Featured Image')
                        ->schema([
                            Forms\Components\ViewField::make('featured_media_id')
                                ->label('Featured image')
                                ->view('filament.forms.components.media-library-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Post $record): void {
                                    $set(
                                        'featured_media_id',
                                        $record?->featuredImage()?->getCustomProperty('source_media_id')
                                            ?: ($record?->featuredImage() ? '__keep_existing__' : null),
                                    );
                                })
                                ->helperText('Choose an existing image from Media Library. Upload new images from Media > Upload Media first.')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('SEO')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('SEO title')
                                ->maxLength(70)
                                ->helperText('Recommended: up to 70 characters. Falls back to the post title when empty.'),
                            Forms\Components\Textarea::make('seo_description')
                                ->maxLength(160)
                                ->helperText('Recommended: up to 160 characters. Falls back to excerpt, content, or site defaults.')
                                ->rows(3)
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('seo_image')
                                ->label('Social image URL')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->helperText('Falls back to the featured image when empty.')
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('robots_index')
                                ->label('Allow search engines to index this post')
                                ->default(true),
                            Forms\Components\Toggle::make('robots_follow')
                                ->label('Allow search engines to follow links')
                                ->default(true),
                            Forms\Components\TextInput::make('seo_keywords')
                                ->label('SEO keywords')
                                ->maxLength(255)
                                ->helperText('Optional legacy meta keywords field.'),
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
                            Forms\Components\DateTimePicker::make('published_at')
                                ->seconds(false)
                                ->helperText('Leave empty to publish immediately when status is Published. Public view actions are shown only for published records.'),
                        ])
                        ->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('featured_image')
                    ->collection('featured_image')
                    ->conversion('thumb')
                    ->label('Image'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.title')
                    ->label('Category')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'title'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Post $record): string => route('admin.preview.posts.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('viewPublic')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Post $record): string => static::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Post $record): bool => static::isPubliclyVisible($record)),
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }

    public static function publicUrl(Post $post): string
    {
        return route('blog.show', $post->slug);
    }

    public static function isPubliclyVisible(Post $post): bool
    {
        return $post->status === 'published'
            && (blank($post->published_at) || $post->published_at->lte(now()));
    }

    protected static function slugFromTranslatedTitle(?string $title): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            return '';
        }

        $words = array_slice(
            preg_split('/\s+/u', $title, flags: PREG_SPLIT_NO_EMPTY) ?: [],
            0,
            3,
        );

        $translatedWords = array_filter(
            array_map(fn (string $word): string => static::translateSlugWord($word), $words),
        );

        $slugSource = $translatedWords === []
            ? implode(' ', $words)
            : implode(' ', $translatedWords);

        return Str::slug($slugSource);
    }

    protected static function translateSlugWord(string $word): string
    {
        $normalizedWord = static::normalizePersianSlugWord($word);

        $translations = [
            'آموزش' => 'training',
            'اخبار' => 'news',
            'استراتژی' => 'strategy',
            'اصول' => 'principles',
            'افزایش' => 'increase',
            'بازاریابی' => 'marketing',
            'بررسی' => 'review',
            'برنامه' => 'program',
            'بهینه' => 'optimized',
            'بهینه‌سازی' => 'optimization',
            'تجارت' => 'business',
            'تجربه' => 'experience',
            'تحلیل' => 'analysis',
            'توسعه' => 'development',
            'دیجیتال' => 'digital',
            'راهنمای' => 'guide',
            'راهکار' => 'solution',
            'روش' => 'method',
            'روش‌های' => 'methods',
            'رشد' => 'growth',
            'سئو' => 'seo',
            'سایت' => 'website',
            'ساخت' => 'build',
            'طراحی' => 'design',
            'فروش' => 'sales',
            'فروشگاه' => 'store',
            'فروشگاهی' => 'store',
            'کسب' => 'business',
            'کسب‌وکار' => 'business',
            'کسب‌وکارها' => 'businesses',
            'کسب‌وکارهای' => 'businesses',
            'مدیریت' => 'management',
            'محتوا' => 'content',
            'محصول' => 'product',
            'مشتری' => 'customer',
            'مشتریان' => 'customers',
            'مقاله' => 'article',
            'نکات' => 'tips',
            'وب' => 'web',
            'وب‌سایت' => 'website',
        ];

        return $translations[$normalizedWord] ?? Str::slug($word);
    }

    protected static function normalizePersianSlugWord(string $word): string
    {
        $word = str_replace(
            ['ي', 'ك', 'ة', 'ۀ', "\u{200C}"],
            ['ی', 'ک', 'ه', 'ه', '‌'],
            trim($word),
        );

        return preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $word) ?? '';
    }
}
