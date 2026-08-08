<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\UsersRelationManager;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $slug = 'client-portal/customers';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات مشتری')
                ->schema([
                    Forms\Components\TextInput::make('display_name')->label('نام نمایشی')->required()->maxLength(255),
                    Forms\Components\TextInput::make('company_name')->label('نام شرکت')->maxLength(255),
                    Forms\Components\TextInput::make('mobile')->label('موبایل تماس')->tel()->maxLength(32),
                    Forms\Components\TextInput::make('email')->label('ایمیل تماس')->email()->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->required()
                        ->options(self::statusOptions())
                        ->default(Customer::STATUS_ACTIVE),
                    Forms\Components\Textarea::make('address')->label('آدرس')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')->label('یادداشت داخلی')->rows(5)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->label('نام نمایشی')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company_name')->label('شرکت')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('mobile')->label('موبایل')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('users_count')->label('کاربران')->counts('users'),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('مشاهده'),
                Tables\Actions\EditAction::make()->label('ویرایش'),
            ])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('اطلاعات مشتری')->schema([
                Infolists\Components\TextEntry::make('display_name')->label('نام نمایشی'),
                Infolists\Components\TextEntry::make('company_name')->label('نام شرکت')->placeholder('—'),
                Infolists\Components\TextEntry::make('mobile')->label('موبایل تماس')->placeholder('—'),
                Infolists\Components\TextEntry::make('email')->label('ایمیل تماس')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Infolists\Components\TextEntry::make('address')->label('آدرس')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('notes')->label('یادداشت داخلی')->placeholder('—')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [UsersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            Customer::STATUS_ACTIVE => 'فعال',
            Customer::STATUS_INACTIVE => 'غیرفعال',
            Customer::STATUS_ARCHIVED => 'بایگانی‌شده',
        ];
    }
}
