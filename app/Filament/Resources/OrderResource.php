<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\ModuleService;
use App\Services\OrderPaymentService;
use App\Services\ProductPricingService;
use App\Services\ZarinpalPaymentGateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components as Info;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = Order::class;

    protected static ?string $navigationGroup = 'خدمات و فروش';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->shopEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات سفارش')
                ->schema([
                    Forms\Components\TextInput::make('public_order_code')->label('کد سفارش')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('order_number')->label('شناسه داخلی')->disabled()->dehydrated(false),
                    Forms\Components\Select::make('status')
                        ->disabled()->dehydrated(false)
                        ->options([
                            Order::STATUS_PENDING => 'در انتظار',
                            Order::STATUS_PAID => 'پرداخت‌شده',
                            Order::STATUS_CANCELLED => 'لغوشده',
                            Order::STATUS_COMPLETED => 'تکمیل‌شده',
                        ]),
                    Forms\Components\Select::make('payment_status')
                        ->disabled()->dehydrated(false)
                        ->options([
                            Order::PAYMENT_STATUS_UNPAID => 'پرداخت‌نشده',
                            Order::PAYMENT_STATUS_PAID => 'پرداخت‌شده',
                            Order::PAYMENT_STATUS_FAILED => 'ناموفق',
                        ]),
                    Forms\Components\TextInput::make('payment_method')->label('روش پرداخت')->formatStateUsing(fn (?string $state): string => match ($state) {
                        'manual' => 'پرداخت دستی', 'zarinpal' => 'درگاه زرین‌پال', default => '—'
                    })->disabled()->dehydrated(false),
                    Forms\Components\Placeholder::make('payment_provenance')
                        ->label('منشأ تأیید پرداخت')
                        ->content(fn (?Order $record): string => $record?->paymentProvenanceLabel() ?? 'بدون تأیید پرداخت'),
                    Forms\Components\TextInput::make('subtotal')->label('جمع اقلام')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state))->disabled()->suffix('تومان'),
                    Forms\Components\TextInput::make('total')->label('مبلغ نهایی')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state))->disabled()->suffix('تومان'),
                ])
                ->columns(2),
            Forms\Components\Section::make('تاریخچه پرداخت')
                ->schema([
                    Forms\Components\Repeater::make('paymentAttempts')
                        ->relationship()->disabled()->dehydrated(false)
                        ->saveRelationshipsWhenDisabled(false)
                        ->schema([
                            Forms\Components\TextInput::make('provider')->label('روش')->formatStateUsing(fn (?string $state, ?PaymentAttempt $record): string => $record?->providerLabel() ?? '—'),
                            Forms\Components\TextInput::make('environment')->label('محیط درگاه')->formatStateUsing(fn (?string $state, ?PaymentAttempt $record): string => $record?->environmentLabel() ?? '—'),
                            Forms\Components\TextInput::make('status')->label('وضعیت تلاش')->formatStateUsing(fn (?string $state, ?PaymentAttempt $record): string => $record?->statusLabel() ?? '—'),
                            Forms\Components\TextInput::make('amount')->label('مبلغ دقیق')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state)),
                            Forms\Components\TextInput::make('currency')->label('ارز'),
                            Forms\Components\TextInput::make('amount_unit')->label('واحد مبلغ'),
                            Forms\Components\TextInput::make('gateway_amount_unit')->label('واحد درگاه'),
                            Forms\Components\TextInput::make('gateway_amount')->label('مبلغ ارسالی درگاه')->formatStateUsing(fn (mixed $state): ?string => filled($state) ? app(ProductPricingService::class)->formatAmount((string) $state) : null),
                            Forms\Components\TextInput::make('requested_at')->label('زمان شروع درخواست'),
                            Forms\Components\TextInput::make('diagnostics.code')->label('آخرین نتیجه بررسی')->formatStateUsing(fn (?string $state, ?PaymentAttempt $record): string => $record?->diagnosticLabel() ?? '—'),
                            Forms\Components\TextInput::make('source')->label('منشأ تأیید')->formatStateUsing(fn (?string $state, ?PaymentAttempt $record): string => $record?->sourceLabel() ?? '—'),
                            Forms\Components\TextInput::make('verification_outcome')->label('نتیجه')->formatStateUsing(fn (?string $state, ?PaymentAttempt $record): string => $record?->verificationOutcomeLabel() ?? '—'),
                            Forms\Components\TextInput::make('authority')->label('شناسه درخواست درگاه'),
                            Forms\Components\TextInput::make('provider_reference')->label('مرجع درگاه'),
                            Forms\Components\TextInput::make('confirmed_by')->label('شناسه تأییدکننده'),
                            Forms\Components\TextInput::make('actor_snapshot.name')->label('نام تأییدکننده در زمان ثبت'),
                            Forms\Components\TextInput::make('confirmed_at')->label('زمان تأیید دستی'),
                            Forms\Components\TextInput::make('verified_at')->label('زمان تأیید درگاه'),
                            Forms\Components\TextInput::make('manual_reference')->label('مرجع دستی'),
                            Forms\Components\Textarea::make('confirmation_reason')->label('دلیل تأیید'),
                        ])->columns(2)->addable(false)->deletable(false)->reorderable(false),
                ]),
            Forms\Components\Section::make('اطلاعات مشتری')
                ->schema([
                    Forms\Components\TextInput::make('customer_name')->label('نام مشتری')->disabled(),
                    Forms\Components\TextInput::make('customer_phone')->label('تلفن')->disabled(),
                    Forms\Components\TextInput::make('customer_email')->label('ایمیل')->disabled(),
                    Forms\Components\Textarea::make('customer_address')->label('نشانی')->disabled()->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')->label('توضیحات مشتری')->disabled()->rows(3)->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('اقلام سفارش')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->disabled()->dehydrated(false)->saveRelationshipsWhenDisabled(false)
                        ->label('اقلام سفارش')
                        ->schema([
                            Forms\Components\TextInput::make('product_title')->label('کالا')->disabled(),
                            Forms\Components\TextInput::make('product_sku')->label('شناسه کالا')->disabled(),
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
            Forms\Components\Section::make('یادداشت داخلی مدیر')
                ->schema([
                    Forms\Components\Textarea::make('admin_note')
                        ->label('یادداشت')
                        ->rows(4)
                        ->maxLength(2000)
                        ->helperText('این یادداشت فقط برای مدیران است و به مشتری نمایش داده نمی‌شود.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['items', 'user']))
            ->recordUrl(fn (Order $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('public_order_code')
                    ->label('کد سفارش')
                    ->state(fn (Order $record): string => $record->displayCode())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $orders): Builder => $orders
                            ->where('public_order_code', 'like', "%{$search}%")
                            ->orWhere('order_number', 'like', "%{$search}%")))
                    ->copyable()
                    ->copyMessage('کد سفارش کپی شد')
                    ->description(fn (Order $record): string => 'شناسه داخلی: '.$record->order_number)
                    ->tooltip(fn (Order $record): string => 'شناسه داخلی: '.$record->order_number),
                Tables\Columns\TextColumn::make('customer_name')->label('مشتری')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('حساب مالک')
                    ->placeholder('بدون مالک / قدیمی')->searchable()
                    ->url(fn (Order $record): ?string => $record->user && ! $record->user->isAdmin()
                        ? SiteUserResource::getUrl('view', ['record' => $record->user]) : null),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('تلفن')
                    ->searchable()
                    ->url(fn (Order $record): ?string => filled($record->customer_phone) ? 'tel:'.$record->customer_phone : null),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت سفارش')
                    ->formatStateUsing(fn (?string $state, Order $record): string => $record->statusLabel())
                    ->icon(fn (Order $record): string => $record->statusIcon())
                    ->badge()
                    ->color(fn (Order $record): string => $record->statusColor())
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('وضعیت پرداخت')
                    ->formatStateUsing(fn (?string $state, Order $record): string => $record->paymentStatusLabel())
                    ->icon(fn (Order $record): string => $record->paymentStatusIcon())
                    ->badge()
                    ->color(fn (Order $record): string => $record->paymentStatusColor())
                    ->sortable(),
                Tables\Columns\TextColumn::make('items.product_title')
                    ->label('اقلام سفارش')
                    ->state(fn (Order $record): string => $record->items->take(2)->pluck('product_title')->implode('، ')
                        .($record->items->count() > 2 ? ' و '.($record->items->count() - 2).' قلم دیگر' : ''))
                    ->placeholder('بدون قلم')->limit(42),
                Tables\Columns\TextColumn::make('total')
                    ->label('مبلغ کل')
                    ->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state).' تومان')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ثبت')->jalaliDateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('کاربر سایت')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')->options([
                    Order::STATUS_PENDING => 'در انتظار',
                    Order::STATUS_PAID => 'پرداخت‌شده',
                    Order::STATUS_CANCELLED => 'لغوشده',
                    Order::STATUS_COMPLETED => 'تکمیل‌شده',
                ])->label('وضعیت سفارش'),
                Tables\Filters\SelectFilter::make('payment_status')->label('وضعیت پرداخت')->options([
                    Order::PAYMENT_STATUS_UNPAID => 'پرداخت‌نشده',
                    Order::PAYMENT_STATUS_PAID => 'پرداخت‌شده',
                    Order::PAYMENT_STATUS_FAILED => 'ناموفق',
                ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->jalali()->label('از تاریخ'),
                        Forms\Components\DatePicker::make('created_until')->jalali()->label('تا تاریخ'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->actions([Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('view')->label('مشاهده جزئیات')->icon('heroicon-o-eye')
                    ->url(fn (Order $record): string => static::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('edit')->label('ویرایش یادداشت داخلی')->icon('heroicon-o-pencil-square')
                    ->url(fn (Order $record): string => static::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('print')->label('چاپ فاکتور')->icon('heroicon-o-printer')
                    ->url(fn (Order $record): string => route('admin.orders.print', $record))->openUrlInNewTab(),
                ...static::operationActions(Tables\Actions\Action::class),
            ])->label('عملیات')->icon('heroicon-m-ellipsis-vertical')->button()], Tables\Enums\ActionsPosition::BeforeColumns)
            ->actionsColumnLabel('عملیات');
    }

    /** The same audited state changes are shared by the list and the order detail page. */
    public static function operationActions(string $actionClass): array
    {
        return [
            $actionClass::make('markPaid')->label('تأیید دستی پرداخت')->icon('heroicon-o-banknotes')->color('success')
                ->requiresConfirmation()->form([
                    Forms\Components\Textarea::make('reason')->label('دلیل یا شرح مدرک پرداخت')->required()->maxLength(2000),
                    Forms\Components\TextInput::make('reference')->label('شماره پیگیری دستی')->maxLength(255),
                ])
                ->visible(fn (Order $record): bool => ! $record->isPaid() && $record->payment_method === 'manual'
                    && ! in_array($record->status, [Order::STATUS_CANCELLED, Order::STATUS_PAID], true))
                ->action(fn (Order $record, array $data) => app(OrderPaymentService::class)->confirmManual(
                    $record, Auth::guard('web')->user(), $data['reason'] ?? '', $data['reference'] ?? null)),
            $actionClass::make('reconcileZarinpal')->label('بررسی پرداخت زرین‌پال')->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->visible(fn (Order $record): bool => $record->payment_method === 'zarinpal' && ! $record->isPaid())
                ->action(function (Order $record): void {
                    app(OrderPaymentService::class)->authorizeAdministrator(Auth::guard('web')->user());
                    app(ZarinpalPaymentGateway::class)->reconcilePayment($record);
                }),
            $actionClass::make('markCompleted')->label('تکمیل سفارش')->icon('heroicon-o-check-circle')->color('success')
                ->modalDescription('تکمیل سفارش وضعیت پرداخت را تغییر نمی‌دهد.')
                ->requiresConfirmation()
                ->visible(fn (Order $record): bool => ! in_array($record->status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true))
                ->action(fn (Order $record): bool => $record->markCompleted()),
            $actionClass::make('cancel')->label('لغو سفارش')->icon('heroicon-o-x-circle')->color('danger')
                ->requiresConfirmation()->modalDescription('لغو سفارش، پرداخت را مسترد نمی‌کند.')
                ->visible(fn (Order $record): bool => $record->canBeCancelled())
                ->action(fn (Order $record): bool => $record->cancel()),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Info\Section::make('نمای کلی سفارش')->icon('heroicon-o-receipt-refund')->schema([
                Info\TextEntry::make('public_order_code')->label('کد سفارش')->copyable()->copyMessage('کد سفارش کپی شد')
                    ->state(fn (Order $record): string => $record->displayCode()),
                Info\TextEntry::make('order_number')->label('شناسه داخلی')->copyable()->color('gray'),
                Info\TextEntry::make('status')->label('وضعیت سفارش')->badge()
                    ->formatStateUsing(fn (string $state, Order $record): string => $record->statusLabel())
                    ->icon(fn (Order $record): string => $record->statusIcon())->color(fn (Order $record): string => $record->statusColor()),
                Info\TextEntry::make('payment_status')->label('وضعیت پرداخت')->badge()
                    ->formatStateUsing(fn (string $state, Order $record): string => $record->paymentStatusLabel())
                    ->icon(fn (Order $record): string => $record->paymentStatusIcon())->color(fn (Order $record): string => $record->paymentStatusColor()),
                Info\TextEntry::make('payment_method')->label('روش پرداخت')
                    ->formatStateUsing(fn (?string $state, Order $record): string => $record->paymentMethodLabel()),
                Info\TextEntry::make('payment_provenance')->label('منشأ تأیید پرداخت')
                    ->state(fn (Order $record): string => $record->paymentProvenanceLabel()),
                Info\TextEntry::make('subtotal')->label('جمع اقلام')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state))->suffix(' تومان'),
                Info\TextEntry::make('total')->label('مبلغ نهایی')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state))->weight('bold')->suffix(' تومان'),
                Info\TextEntry::make('created_at')->label('زمان ثبت')->jalaliDateTime(),
            ])->columns(3),
            Info\Section::make('مشتری')->icon('heroicon-o-user')->schema([
                Info\TextEntry::make('user.name')->label('حساب مالک')->placeholder('بدون مالک / قدیمی')
                    ->url(fn (Order $record): ?string => $record->user && ! $record->user->isAdmin()
                        ? SiteUserResource::getUrl('view', ['record' => $record->user]) : null),
                Info\TextEntry::make('customer_name')->label('نام مشتری'),
                Info\TextEntry::make('customer_phone')->label('تلفن')->url(fn (Order $record): ?string => filled($record->customer_phone) ? 'tel:'.$record->customer_phone : null),
                Info\TextEntry::make('customer_email')->label('ایمیل')->url(fn (Order $record): ?string => filled($record->customer_email) ? 'mailto:'.$record->customer_email : null)->placeholder('ثبت نشده'),
                Info\TextEntry::make('customer_address')->label('نشانی')->placeholder('ثبت نشده')->columnSpanFull(),
                Info\TextEntry::make('notes')->label('توضیحات مشتری')->placeholder('بدون توضیحات')->columnSpanFull(),
            ])->columns(2),
            Info\Section::make('اقلام سفارش')->icon('heroicon-o-shopping-bag')->schema([
                Info\RepeatableEntry::make('items')->hiddenLabel()->schema([
                    Info\TextEntry::make('product_title')->label('کالا'),
                    Info\TextEntry::make('product_sku')->label('شناسه کالا')->placeholder('—'),
                    Info\TextEntry::make('quantity')->label('تعداد')->suffix(' عدد'),
                    Info\TextEntry::make('unit_price')->label('قیمت واحد')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state))->suffix(' تومان'),
                    Info\TextEntry::make('total')->label('جمع')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state))->suffix(' تومان'),
                ])->columns(5),
            ]),
            Info\Section::make('تاریخچه پرداخت')->icon('heroicon-o-credit-card')->schema([
                Info\RepeatableEntry::make('paymentAttempts')->hiddenLabel()->placeholder('برای این سفارش سابقه‌ای ثبت نشده است.')->schema([
                    Info\TextEntry::make('status')->label('وضعیت تلاش')->state(fn (PaymentAttempt $record): string => $record->statusLabel()),
                    Info\TextEntry::make('provider')->label('روش')->state(fn (PaymentAttempt $record): string => $record->providerLabel()),
                    Info\TextEntry::make('environment')->label('محیط')->state(fn (PaymentAttempt $record): string => $record->environmentLabel()),
                    Info\TextEntry::make('amount')->label('مبلغ')->formatStateUsing(fn (mixed $state): string => app(ProductPricingService::class)->formatAmount((string) $state))->suffix(' تومان'),
                    Info\TextEntry::make('source')->label('منشأ تأیید')->state(fn (PaymentAttempt $record): string => $record->sourceLabel()),
                    Info\TextEntry::make('verification_outcome')->label('نتیجه بررسی')->state(fn (PaymentAttempt $record): string => $record->verificationOutcomeLabel()),
                    Info\TextEntry::make('diagnostics.code')->label('آخرین نتیجه بررسی')->state(fn (PaymentAttempt $record): string => $record->diagnosticLabel()),
                    Info\TextEntry::make('provider_reference')->label('مرجع درگاه')->placeholder('—'),
                    Info\TextEntry::make('confirmed_at')->label('زمان تأیید دستی')->jalaliDateTime()->placeholder('—'),
                    Info\TextEntry::make('verified_at')->label('زمان تأیید درگاه')->jalaliDateTime()->placeholder('—'),
                    Info\TextEntry::make('actor_snapshot.name')->label('مدیر تأییدکننده')->placeholder('—'),
                    Info\TextEntry::make('manual_reference')->label('مرجع دستی')->placeholder('—'),
                    Info\TextEntry::make('confirmation_reason')->label('دلیل تأیید')->placeholder('—')->columnSpanFull(),
                ])->columns(3),
            ]),
            Info\Section::make('یادداشت داخلی')->schema([
                Info\TextEntry::make('admin_note')->hiddenLabel()->placeholder('یادداشت داخلی ثبت نشده است.')->columnSpanFull(),
            ])->collapsible()->collapsed(),
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
