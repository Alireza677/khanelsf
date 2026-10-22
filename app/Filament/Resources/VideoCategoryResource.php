<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoCategoryResource\Pages;
use App\Models\VideoCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VideoCategoryResource extends Resource
{
    protected static ?string $model = VideoCategory::class;

    protected static ?string $modelLabel = 'دسته ویدئو';

    protected static ?string $pluralModelLabel = 'دسته‌های ویدئو';

    protected static ?string $navigationGroup = 'محتوای وب‌سایت';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('نام دسته‌بندی')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->label('نامک')->maxLength(255)->unique(ignoreRecord: true)
                ->required(fn (string $operation): bool => $operation === 'edit')
                ->helperText('در صورت خالی بودن هنگام ایجاد، خودکار ساخته می‌شود.'),
            Forms\Components\TextInput::make('sort_order')->label('ترتیب نمایش')->integer()->minValue(0)->maxValue(2147483647)->default(0)->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('name')->label('نام دسته‌بندی')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('slug')->label('نامک')->searchable(),
            Tables\Columns\TextColumn::make('videos_count')->label('تعداد ویدئوها')->counts('videos'),
            Tables\Columns\TextColumn::make('sort_order')->label('ترتیب نمایش')->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make()->label('ویرایش'),
            Tables\Actions\DeleteAction::make()->label('حذف'),
        ])->bulkActions([Tables\Actions\DeleteBulkAction::make()->label('حذف انتخاب‌شده‌ها')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVideoCategories::route('/')];
    }
}
