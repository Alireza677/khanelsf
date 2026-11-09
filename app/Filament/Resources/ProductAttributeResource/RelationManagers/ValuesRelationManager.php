<?php

namespace App\Filament\Resources\ProductAttributeResource\RelationManagers;

use App\Filament\Support\ProductCatalogFields;
use App\Models\ProductAttributeValue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'مقادیر مجاز ویژگی';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('مقدار')->required()->maxLength(255)
                ->rule(fn (?ProductAttributeValue $record) => ProductCatalogFields::uniqueNameRule($record, ProductAttributeValue::class, $this->getOwnerRecord()->id))
                ->helperText('مثلاً فولاد، سفید یا ۰٫۵؛ واحد در تعریف ویژگی مشخص می‌شود.'),
            ProductCatalogFields::status(), ProductCatalogFields::sortOrder(),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('name')->label('مقدار')->searchable(),
            Tables\Columns\TextColumn::make('assignments_count')->label('محصولات')->counts('assignments'),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                ->formatStateUsing(fn (string $state) => $state === 'active' ? 'فعال' : 'غیرفعال')
                ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
        ])->headerActions([Tables\Actions\CreateAction::make()->label('افزودن مقدار')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()
                ->tooltip('مقادیر استفاده‌شده را به جای حذف، غیرفعال کنید.')]);
    }
}
