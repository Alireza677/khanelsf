<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = Order::class;

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->shopEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Order')
                ->schema([
                    Forms\Components\TextInput::make('order_number')->disabled(),
                    Forms\Components\Select::make('status')
                        ->required()
                        ->options([
                            Order::STATUS_PENDING => 'Pending',
                            Order::STATUS_PAID => 'Paid',
                            Order::STATUS_CANCELLED => 'Cancelled',
                            Order::STATUS_COMPLETED => 'Completed',
                        ]),
                    Forms\Components\Select::make('payment_status')
                        ->required()
                        ->options([
                            Order::PAYMENT_STATUS_UNPAID => 'Unpaid',
                            Order::PAYMENT_STATUS_PAID => 'Paid',
                            Order::PAYMENT_STATUS_FAILED => 'Failed',
                        ]),
                    Forms\Components\TextInput::make('payment_method')->maxLength(255),
                    Forms\Components\TextInput::make('subtotal')->disabled()->prefix('IRT')->suffix('تومان'),
                    Forms\Components\TextInput::make('total')->disabled()->prefix('IRT')->suffix('تومان'),
                ])
                ->columns(2),
            Forms\Components\Section::make('Customer')
                ->schema([
                    Forms\Components\TextInput::make('customer_name')->label('Name')->disabled(),
                    Forms\Components\TextInput::make('customer_phone')->label('Phone')->disabled(),
                    Forms\Components\TextInput::make('customer_email')->label('Email')->disabled(),
                    Forms\Components\Textarea::make('customer_address')->label('Address')->disabled()->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')->label('Customer notes')->disabled()->rows(3)->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Items')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->label('Order items')
                        ->schema([
                            Forms\Components\TextInput::make('product_title')->label('Item')->disabled(),
                            Forms\Components\TextInput::make('product_sku')->label('SKU')->disabled(),
                            Forms\Components\TextInput::make('unit_price')->disabled()->prefix('IRT')->suffix('تومان'),
                            Forms\Components\TextInput::make('quantity')->disabled(),
                            Forms\Components\TextInput::make('total')->disabled()->prefix('IRT')->suffix('تومان'),
                        ])
                        ->columns(5)
                        ->deletable(false)
                        ->addable(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Internal admin note')
                ->schema([
                    Forms\Components\Textarea::make('admin_note')
                        ->label('Note')
                        ->rows(4)
                        ->maxLength(2000)
                        ->helperText('Private note for admins. It is not shown to customers.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Order number copied'),
                Tables\Columns\TextColumn::make('customer_name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Phone')
                    ->searchable()
                    ->url(fn (Order $record): ?string => filled($record->customer_phone) ? 'tel:'.$record->customer_phone : null),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable()
                    ->url(fn (Order $record): ?string => filled($record->customer_email) ? 'mailto:'.$record->customer_email : null),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_PAID, Order::STATUS_COMPLETED => 'success',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Order::PAYMENT_STATUS_PAID => 'success',
                        Order::PAYMENT_STATUS_FAILED => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state).' تومان')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->jalaliDateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('کاربر سایت')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')->options([
                    Order::STATUS_PENDING => 'Pending',
                    Order::STATUS_PAID => 'Paid',
                    Order::STATUS_CANCELLED => 'Cancelled',
                    Order::STATUS_COMPLETED => 'Completed',
                ]),
                Tables\Filters\SelectFilter::make('payment_status')->options([
                    Order::PAYMENT_STATUS_UNPAID => 'Unpaid',
                    Order::PAYMENT_STATUS_PAID => 'Paid',
                    Order::PAYMENT_STATUS_FAILED => 'Failed',
                ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->jalali()->label('Created from'),
                        Forms\Components\DatePicker::make('created_until')->jalali()->label('Created until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record): string => route('admin.orders.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => ! $record->isPaid())
                    ->action(fn (Order $record): bool => $record->markPaid()),
                Tables\Actions\Action::make('markCompleted')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status !== Order::STATUS_COMPLETED)
                    ->action(fn (Order $record): bool => $record->markCompleted()),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->canBeCancelled())
                    ->action(fn (Order $record): bool => $record->cancel()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
