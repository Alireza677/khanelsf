<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\SiteUserResource\Pages;
use App\Models\Customer;
use App\Models\User;
use App\Services\CreateCustomerForUser;
use App\Services\CustomerMembershipManager;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SiteUserResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $slug = 'site-users';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_admin', false)
            ->with('customers:id,display_name')
            ->withCount('orders');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('mobile')->label('شماره موبایل')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('email')->label('ایمیل')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ثبت‌نام')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('وضعیت حساب')->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('orders_count')->label('تعداد سفارش‌ها')->sortable(),
                Tables\Columns\TextColumn::make('customer_status')->label('وضعیت ارتباط با مشتری')
                    ->state(fn (User $record): string => self::customerStatus($record))
                    ->badge()
                    ->color(fn (User $record): string => $record->customers->isEmpty() ? 'gray' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('وضعیت حساب')->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('customer_status')
                    ->label('وضعیت مشتری')
                    ->options(['without' => 'بدون مشتری', 'connected' => 'متصل به مشتری'])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(($data['value'] ?? null) === 'without', fn (Builder $query): Builder => $query->doesntHave('customers'))
                        ->when(($data['value'] ?? null) === 'connected', fn (Builder $query): Builder => $query->has('customers'))),
                Tables\Filters\SelectFilter::make('orders_status')
                    ->label('وضعیت سفارش')
                    ->options(['with' => 'دارای سفارش', 'without' => 'بدون سفارش'])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(($data['value'] ?? null) === 'with', fn (Builder $query): Builder => $query->has('orders'))
                        ->when(($data['value'] ?? null) === 'without', fn (Builder $query): Builder => $query->doesntHave('orders'))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('مشاهده'),
                self::connectAction(),
                self::createCustomerAction(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('اطلاعات حساب')->schema([
                Infolists\Components\TextEntry::make('name')->label('نام'),
                Infolists\Components\TextEntry::make('mobile')->label('شماره موبایل'),
                Infolists\Components\TextEntry::make('email')->label('ایمیل')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Infolists\Components\TextEntry::make('created_at')->label('تاریخ ثبت‌نام')->dateTime(),
            ])->columns(2),
            Infolists\Components\Section::make('خریدها')->schema([
                Infolists\Components\TextEntry::make('orders_count')->label('تعداد سفارش‌ها')
                    ->url(fn (User $record): ?string => $record->orders_count > 0
                        ? OrderResource::getUrl('index', [
                            'tableFilters' => ['user_id' => ['value' => $record->getKey()]],
                        ])
                        : null),
            ]),
            Infolists\Components\Section::make('مشتریان متصل')->schema([
                Infolists\Components\RepeatableEntry::make('customers')->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('display_name')->label('مشتری'),
                        Infolists\Components\TextEntry::make('pivot.membership_role')->label('نقش')
                            ->formatStateUsing(fn (string $state): string => self::roleOptions()[$state] ?? $state),
                        Infolists\Components\IconEntry::make('pivot.is_primary')->label('مخاطب اصلی')->boolean(),
                    ])->columns(3),
            ])->visible(fn (User $record): bool => $record->customers->isNotEmpty()),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteUsers::route('/'),
            'view' => Pages\ViewSiteUser::route('/{record}'),
        ];
    }

    public static function statusOptions(): array
    {
        return ['active' => 'فعال', 'inactive' => 'غیرفعال'];
    }

    public static function roleOptions(): array
    {
        return ['owner' => 'مالک / مسئول اصلی', 'member' => 'عضو'];
    }

    private static function customerStatus(User $user): string
    {
        return match ($user->customers->count()) {
            0 => 'مشتری خدماتی نیست',
            1 => 'مشتری: '.$user->customers->first()->display_name,
            default => 'عضو '.$user->customers->count().' مشتری',
        };
    }

    private static function connectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('connectCustomer')
            ->label('اتصال به مشتری موجود')
            ->icon('heroicon-o-link')
            ->visible(fn (User $record): bool => $record->isActive())
            ->form([
                Forms\Components\Select::make('customer_id')->label('مشتری')
                    ->options(fn (User $record): array => Customer::query()
                        ->whereNotIn('id', $record->customers()->select('customers.id'))
                        ->orderBy('display_name')->pluck('display_name', 'id')->all())
                    ->searchable()->required(),
                Forms\Components\Select::make('membership_role')->label('نقش')
                    ->options(self::roleOptions())->default('member')->required(),
                Forms\Components\Toggle::make('is_primary')->label('مخاطب اصلی این مشتری'),
            ])
            ->action(function (User $record, array $data, CustomerMembershipManager $memberships): void {
                $customer = Customer::query()->findOrFail($data['customer_id']);
                $memberships->attach($customer, $record, $data['membership_role'], (bool) ($data['is_primary'] ?? false));
                Notification::make()->success()->title('کاربر به مشتری متصل شد.')->send();
            });
    }

    private static function createCustomerAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('createCustomerAndConnect')
            ->label('ایجاد مشتری و اتصال')
            ->icon('heroicon-o-building-office')
            ->visible(fn (User $record): bool => $record->isActive())
            ->fillForm(fn (User $record): array => [
                'display_name' => $record->name,
                'mobile' => $record->mobile,
                'email' => $record->email,
                'status' => Customer::STATUS_ACTIVE,
            ])
            ->form([
                Forms\Components\TextInput::make('display_name')->label('نام نمایشی')->required()->maxLength(255),
                Forms\Components\TextInput::make('company_name')->label('نام شرکت')->maxLength(255),
                Forms\Components\TextInput::make('mobile')->label('موبایل تماس')->tel()->maxLength(32),
                Forms\Components\TextInput::make('email')->label('ایمیل تماس')->email()->maxLength(255),
                Forms\Components\Select::make('status')->label('وضعیت')->options(CustomerResource::statusOptions())
                    ->default(Customer::STATUS_ACTIVE)->required(),
                Forms\Components\Textarea::make('address')->label('آدرس')->rows(3),
                Forms\Components\Textarea::make('notes')->label('یادداشت داخلی')->rows(3),
            ])
            ->action(function (User $record, array $data, CreateCustomerForUser $creator): void {
                $customer = $creator->handle($record, $data);
                Notification::make()->success()->title('مشتری «'.$customer->display_name.'» ایجاد و کاربر متصل شد.')->send();
            });
    }
}
