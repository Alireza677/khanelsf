<?php

namespace App\Filament\Resources;

use App\Contracts\VideoProviderAdapter;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\VideoResource\Pages;
use App\Models\Media;
use App\Models\Video;
use App\Rules\VideoRelatedUrl;
use App\Services\Video\VideoDuration;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class VideoResource extends Resource
{
    use UsesMediaLibraryImages;

    protected static ?string $model = Video::class;

    protected static ?string $modelLabel = 'ویدئو';

    protected static ?string $pluralModelLabel = 'کتابخانه ویدئویی';

    protected static ?string $navigationGroup = 'محتوای وب‌سایت';

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('محتوای ویدئو')->schema([
                Forms\Components\TextInput::make('title')->label('عنوان')->required()->maxLength(255),
                Forms\Components\TextInput::make('slug')->label('نامک')->maxLength(255)
                    ->required(fn (string $operation): bool => $operation === 'edit')
                    ->unique(ignoreRecord: true)
                    ->helperText('در صورت خالی بودن هنگام ایجاد، خودکار ساخته می‌شود؛ با ویرایش عنوان تغییر نمی‌کند.'),
                Forms\Components\TextInput::make('source_url')->label('لینک آپارات')->required()->maxLength(2048)
                    ->placeholder('https://www.aparat.com/v/...')->columnSpanFull()
                    ->rules([fn (?Video $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $source = app(VideoProviderAdapter::class)->parseUrl((string) $value);
                        if ($source === null) {
                            $fail('لینک HTTPS معتبر ویدئو در aparat.com/v/... را وارد کنید.');
                        } elseif (Video::query()->where('provider', $source['provider'])->where('external_id', $source['external_id'])
                            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))->exists()) {
                            $fail('این ویدئوی آپارات قبلاً در کتابخانه ثبت شده است.');
                        }
                    }]),
                Forms\Components\Textarea::make('excerpt')->label('خلاصه کوتاه')->maxLength(2000)->rows(3)->columnSpanFull(),
                Forms\Components\RichEditor::make('description')->label('توضیحات کامل')
                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])->columnSpanFull(),
                Forms\Components\TextInput::make('duration_seconds')->label('مدت ویدئو')->placeholder('05:30 یا 01:05:30')
                    ->formatStateUsing(fn (?int $state): ?string => VideoDuration::format($state))
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        try {
                            VideoDuration::parse($value);
                        } catch (InvalidArgumentException) {
                            $fail('مدت را به صورت mm:ss یا hh:mm:ss وارد کنید.');
                        }
                    }])
                    ->dehydrateStateUsing(fn (?string $state): ?int => VideoDuration::parse($state))
                    ->helperText('اگر خالی باشد، در صورت دسترس‌بودن از آپارات دریافت می‌شود.'),
                Forms\Components\Select::make('categories')->label('دسته‌بندی‌ها')
                    ->relationship('categories', 'name')->multiple()->searchable()->preload(),
                Forms\Components\TextInput::make('related_url')->label('لینک مرتبط')->maxLength(2048)
                    ->rules([new VideoRelatedUrl])->placeholder('/services/example')->columnSpanFull(),
            ])->columns(2),
            Forms\Components\Section::make('تصویر و انتشار')->schema([
                Forms\Components\ViewField::make('featured_media_id')->label('تصویر جایگزین')
                    ->view('filament.forms.components.media-library-picker')
                    ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Set $set, ?Video $record) => $set('featured_media_id', static::mediaLibraryFeaturedState($record)))
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && Media::reusableImage($value) === null) {
                            $fail('یک تصویر معتبر از کتابخانه رسانه انتخاب کنید.');
                        }
                    }])
                    ->helperText('اولویت: تصویر انتخاب‌شده، تصویر ذخیره‌شده آپارات، تصویر پیش‌فرض. برای افزودن تصویر از کتابخانه رسانه استفاده کنید.')
                    ->columnSpanFull(),
                Forms\Components\ViewField::make('cached_thumbnail')->label('تصویر خودکار آپارات')
                    ->view('filament.forms.components.video-provider-thumbnail')
                    ->viewData(fn (?Video $record): array => [
                        'thumbnailUrl' => app(VideoProviderAdapter::class)->trustedThumbnailUrl($record?->thumbnail_url),
                    ])->dehydrated(false),
                Forms\Components\Select::make('status')->label('وضعیت انتشار')
                    ->options(['draft' => 'پیش‌نویس', 'published' => 'منتشرشده'])->default('draft')->required(),
                Forms\Components\DateTimePicker::make('published_at')->label('زمان انتشار')->jalali()
                    ->helperText('ویدئوی منتشرشده با زمان آینده تا آن زمان نمایش داده نمی‌شود.'),
                Forms\Components\TextInput::make('sort_order')->label('ترتیب نمایش')
                    ->integer()->minValue(0)->maxValue(2147483647)->default(0)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->modifyQueryUsing(fn (Builder $query) => $query->with(['categories', 'media', 'mediaUsages.media']))
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')->label('تصویر')->getStateUsing(fn (Video $record): string => $record->thumbnailUrl()),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('categories.name')->label('دسته‌بندی‌ها')->badge(),
                Tables\Columns\TextColumn::make('duration_seconds')->label('مدت')->formatStateUsing(fn (?int $state): ?string => VideoDuration::format($state))->sortable(),
                Tables\Columns\TextColumn::make('status')->label('وضعیت انتشار')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'منتشرشده' : 'پیش‌نویس'),
                Tables\Columns\TextColumn::make('published_at')->label('زمان انتشار')->jalaliDateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ایجاد')->jalaliDateTime()->sortable()->toggleable(),
            ])->filters([
                Tables\Filters\SelectFilter::make('status')->label('وضعیت انتشار')->options(['draft' => 'پیش‌نویس', 'published' => 'منتشرشده']),
                Tables\Filters\SelectFilter::make('categories')->label('دسته‌بندی')->relationship('categories', 'name')->multiple()->preload(),
                Tables\Filters\Filter::make('scheduled')->label('زمان‌بندی‌شده')
                    ->query(fn (Builder $query) => $query->where('status', 'published')->where('published_at', '>', now())),
            ])->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('حذف انتخاب‌شده‌ها'),
            ])->emptyStateHeading('هنوز ویدئویی ثبت نشده است');
    }

    public static function refreshProviderMetadata(Video $record): void
    {
        if ($record->refreshMetadata()) {
            Notification::make()->title('اطلاعات تصویر و مدت آپارات به‌روزرسانی شد.')->success()->send();
        } else {
            Notification::make()->title('اطلاعات آپارات در دسترس نیست؛ اطلاعات فعلی حفظ شد.')->warning()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideos::route('/'),
            'create' => Pages\CreateVideo::route('/create'),
            'edit' => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}
