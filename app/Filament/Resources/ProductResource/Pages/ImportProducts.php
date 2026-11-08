<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Services\ProductImport\ProductImportService;
use App\Services\ProductImport\ProductImportTemplate;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ImportProducts extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.products.import';

    protected static ?string $title = 'درون‌ریزی محصولات';

    #[Locked]
    public ?int $importId = null;

    public function mount(): void
    {
        $this->guard();
        if (request()->filled('batch')) {
            $this->importId = (int) request()->query('batch');
            $this->currentImport();
        }
    }

    public function currentImport(): ?ProductImport
    {
        if (! $this->importId) {
            return null;
        }
        $import = ProductImport::query()->findOrFail($this->importId);
        $this->guard($import);

        return $import;
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\Action::make('upload')
                ->label('آپلود فایل اکسل')->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('۱. آپلود و بررسی فایل محصولات')
                ->modalDescription('فایل فقط بررسی می‌شود. ایجاد یا تغییر محصول پس از پیش‌نمایش و تأیید نهایی انجام می‌شود.')
                ->modalSubmitActionLabel('اعتبارسنجی و پیش‌نمایش')
                ->form([
                    Forms\Components\FileUpload::make('file')->label('فایل XLSX')
                        ->disk('product_imports')->visibility('private')->storeFiles(false)
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)->required()
                        ->helperText('حداکثر ۱۰ مگابایت. شیت Import_Products الزامی است. فرمول‌ها پذیرفته نمی‌شوند.'),
                ])
                ->action(function (array $data, ProductImportService $service): void {
                    $this->guard();
                    abort_unless(($data['file'] ?? null) instanceof UploadedFile, 422);
                    try {
                        $import = $service->stage($data['file'], auth()->user());
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([
                            $this->getMountedActionForm()->getStatePath().'.file' => Arr::flatten($exception->errors()),
                        ]);
                    }
                    $this->redirect(ProductResource::getUrl('import', ['batch' => $import->id]));
                }),
            Actions\Action::make('template')->label('دریافت قالب اکسل محصولات')->color('gray')
                ->icon('heroicon-o-arrow-down-tray')->action(function (ProductImportTemplate $template) {
                    $this->guard();

                    return $template->download();
                }),
            Actions\Action::make('history')->label('سوابق درون‌ریزی')->color('gray')
                ->form([Forms\Components\Select::make('batch')->label('فایل قبلی')->required()->searchable()
                    ->options(fn () => ProductImport::query()->where('user_id', auth()->id())->latest()->limit(50)->get()
                        ->mapWithKeys(fn ($batch) => [$batch->id => '#'.$batch->id.' '.$batch->original_filename.' · '.$batch->created_at->format('Y-m-d H:i')])->all())])
                ->action(function (array $data): void {
                    $this->guard(ProductImport::query()->findOrFail($data['batch']));
                    $this->redirect(ProductResource::getUrl('import', ['batch' => $data['batch']]));
                }),
            Actions\Action::make('configure')->label('۲. روش ثبت و تطبیق دسته‌ها')->color('gray')
                ->visible(fn () => $this->currentImport()?->status === 'preview')
                ->modalDescription('دسته جدید فقط پس از انتخاب صریح «ایجاد دسته جدید» و تأیید نهایی ساخته می‌شود. برای دسته هم‌نام، ابتدا گزینه‌های موجود را بررسی کنید.')
                ->form([
                    Forms\Components\Select::make('mode')->label('روش ثبت')->required()
                        ->options(['create_only' => 'فقط ایجاد؛ محصولات موجود بدون تغییر رد شوند', 'create_update' => 'ایجاد و به‌روزرسانی ردیف‌های تأییدشده'])
                        ->default(fn () => $this->currentImport()?->mode ?? 'create_only'),
                    Forms\Components\Repeater::make('categories')->label('دسته‌های استفاده‌شده در فایل')
                        ->default(fn () => $this->categoryChoices())->addable(false)->deletable(false)->reorderable(false)
                        ->schema([
                            Forms\Components\Hidden::make('source_slug'),
                            Forms\Components\TextInput::make('source_name')->label('دسته منبع')->disabled()->dehydrated(false),
                            Forms\Components\Select::make('target')->label('تصمیم دسته‌بندی')->required()->searchable()
                                ->options(fn () => ['pending' => 'نیازمند بررسی']
                                    + (ProductCategoryResource::canCreate() ? ['create' => 'ایجاد دسته جدید با نامک منبع؛ تأیید صریح'] : [])
                                    + ProductCategory::query()->orderBy('name')->get()->mapWithKeys(fn ($category) => [$category->id => $category->name.' / '.$category->slug])->all()),
                        ]),
                ])
                ->action(function (array $data, ProductImportService $service): void {
                    $import = $this->currentImport();
                    $mapping = collect($data['categories'])->mapWithKeys(fn ($row) => [$row['source_slug'] => $row['target']])->all();
                    $service->configure($import, auth()->user(), $data['mode'], $mapping);
                    $this->resetTable();
                    Notification::make()->success()->title('پیش‌نمایش با تصمیم‌های جدید به‌روز شد.')->send();
                }),
            Actions\Action::make('refresh')->label('بررسی مجدد تعارض‌ها')->color('gray')
                ->visible(fn () => $this->currentImport()?->status === 'preview')
                ->action(function (ProductImportService $service): void {
                    $service->refresh($this->currentImport(), auth()->user());
                    $this->resetTable();
                }),
            Actions\Action::make('report')->label('دریافت گزارش')->icon('heroicon-o-document-arrow-down')->color('gray')
                ->visible(fn () => $this->importId !== null)
                ->action(fn (ProductImportService $service) => $service->report($this->currentImport(), auth()->user())),
            Actions\Action::make('execute')->label('۴. تأیید و اجرای درون‌ریزی')->icon('heroicon-o-check-circle')
                ->visible(function (): bool {
                    $batch = $this->currentImport();

                    return $batch?->status === 'preview' || ($batch?->status === 'processing' && $batch->updated_at->lt(now()->subMinutes(10)));
                })
                ->requiresConfirmation()->modalHeading('تأیید ثبت در فروشگاه')
                ->modalDescription('فقط ردیف‌های آماده در دیتابیس همین سایت ثبت می‌شوند. ردیف‌های خطادار، تکراری یا نیازمند تصمیم رد می‌شوند. محصولات موجود فقط در حالت به‌روزرسانی و با تأیید ردیف تغییر می‌کنند.')
                ->form([Forms\Components\Checkbox::make('confirm')->label('پیش‌نمایش و تصمیم‌ها را بررسی کرده‌ام و اجرای ثبت را تأیید می‌کنم.')->required()->rules(['accepted'])])
                ->modalSubmitActionLabel('تأیید و ثبت ردیف‌های آماده')
                ->action(function (array $data, ProductImportService $service): void {
                    $batch = $service->execute($this->currentImport(), auth()->user(), (bool) ($data['confirm'] ?? false), 20);
                    $this->resetTable();
                    Notification::make()->success()->title($batch->status === 'completed'
                        ? 'پردازش پایان یافت؛ نتیجه هر ردیف و گزارش نهایی را بررسی کنید.'
                        : 'پردازش آغاز شد؛ صفحه را تا پایان باز نگه دارید.')->send();
                }),
        ];

        return [
            $actions[0],
            Actions\ActionGroup::make(array_slice($actions, 1, 5))
                ->label('تنظیمات و گزارش')->icon('heroicon-o-adjustments-horizontal')->button()->color('gray'),
            $actions[6],
        ];
    }

    public function table(Table $table): Table
    {
        return $table->query(fn () => ProductImportRow::query()->where('product_import_id', $this->currentImport()?->id ?? 0))
            ->defaultSort('sheet_row')->paginated([10, 25, 50, 100])->defaultPaginationPageOption(25)
            ->emptyStateHeading('فایل محصولات را بارگذاری کنید')
            ->columns([
                Tables\Columns\TextColumn::make('sheet_row')->label('ردیف اکسل')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان محصول')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->copyable()->extraAttributes(['dir' => 'ltr']),
                Tables\Columns\TextColumn::make('category_slug')->label('دسته‌بندی')->searchable()->wrap()
                    ->formatStateUsing(fn ($state, ProductImportRow $record) => $record->payload['category_name'] ?? $state)
                    ->description(fn (ProductImportRow $record) => $record->category_slug),
                Tables\Columns\TextColumn::make('payload.price')->label('قیمت (تومان)'),
                Tables\Columns\TextColumn::make('payload.status')->label('انتشار')->default('draft')
                    ->formatStateUsing(fn ($state) => ['published' => 'منتشرشده', 'draft' => 'پیش‌نویس'][$state] ?? $state),
                Tables\Columns\TextColumn::make('action')->label('عملیات پیشنهادی')->badge()
                    ->formatStateUsing(fn ($state) => ProductImportRow::ACTION_LABELS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'error', 'duplicate' => 'danger', 'review' => 'warning', default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('result')->label('نتیجه')->badge()->placeholder('ثبت نشده')
                    ->formatStateUsing(fn ($state) => ProductImportRow::RESULT_LABELS[$state] ?? $state),
                Tables\Columns\TextColumn::make('errors')->label('خطاها')->listWithLineBreaks()->wrap()->color('danger'),
                Tables\Columns\TextColumn::make('warnings')->label('هشدارها')->listWithLineBreaks()->wrap()->color('warning'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('action')->label('وضعیت بررسی')->options(ProductImportRow::ACTION_LABELS)])
            ->actions([
                Tables\Actions\Action::make('decide')->label('تطبیق و تصمیم')->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn () => $this->currentImport()?->status === 'preview')
                    ->modalDescription('انتخاب محصول، اتصال صریح به رکورد موجود است. تغییر SKU یا نامک فقط با تأیید جداگانه انجام می‌شود. در حالت فقط ایجاد، رکورد موجود تغییر نمی‌کند.')
                    ->fillForm(fn (ProductImportRow $record) => $record->decision + ['product_id' => $record->product_id])
                    ->form([
                        Forms\Components\Placeholder::make('identity_comparison')->label('مقایسه هویت')
                            ->content(function (ProductImportRow $record): string {
                                $current = $record->product_id ? Product::find($record->product_id) : null;

                                return 'فعلی: '.($current ? '#'.$current->id.' / '.$current->sku.' / '.$current->slug : 'هنوز تطبیق داده نشده')
                                    .' — فایل: '.$record->sku.' / '.($record->payload['slug'] ?? '');
                            }),
                        Forms\Components\Select::make('product_id')->label('اتصال به محصول موجود')->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Product::query()->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%')->orWhere('id', $search))->limit(50)->get()->mapWithKeys(fn ($product) => [$product->id => '#'.$product->id.' '.$product->title.' / '.$product->sku])->all())
                            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->title),
                        Forms\Components\Toggle::make('update')->label('به‌روزرسانی همین ردیف را در حالت ایجاد و به‌روزرسانی تأیید می‌کنم.'),
                        Forms\Components\Toggle::make('confirm_identity')->label('تغییر SKU یا نامک این محصول مطابق فایل را صریحاً تأیید می‌کنم.'),
                        Forms\Components\Toggle::make('allow_similar')->label('این کالا با موارد مشابه متفاوت است؛ ایجاد محصول جدید را تأیید می‌کنم.'),
                    ])
                    ->action(function (ProductImportRow $record, array $data, ProductImportService $service): void {
                        $service->decide($this->currentImport(), $record, auth()->user(), $data);
                        $this->resetTable();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approveUpdates')->label('تأیید به‌روزرسانی موارد انتخاب‌شده')
                    ->visible(fn () => $this->currentImport()?->status === 'preview' && $this->currentImport()?->mode === 'create_update')
                    ->requiresConfirmation()->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, ProductImportService $service): void {
                        $service->approveUpdates($this->currentImport(), auth()->user(), $records->modelKeys());
                        $this->resetTable();
                    }),
            ]);
    }

    private function categoryChoices(): array
    {
        $import = $this->currentImport();
        if (! $import) {
            return [];
        }

        return $import->rows()->where('category_slug', '!=', '')->get()->unique('category_slug')->map(function ($row) use ($import) {
            $slug = mb_strtolower(trim($row->category_slug));
            $existing = ProductCategory::query()->whereRaw('LOWER(TRIM(slug)) = ?', [$slug])->first();

            return [
                'source_slug' => $slug,
                'source_name' => ($import->categories[$slug]['name'] ?? $row->payload['category_name'] ?? $slug).' / '.$row->category_slug,
                'target' => $existing?->id ?? $import->options['categories'][$slug] ?? 'pending',
            ];
        })->values()->all();
    }

    public function continueImport(): void
    {
        $batch = $this->currentImport();
        if ($batch?->status !== 'processing') {
            return;
        }
        $batch = app(ProductImportService::class)->continueExecution($batch, auth()->user());
        if ($batch->status === 'completed') {
            Notification::make()->success()->title('درون‌ریزی پایان یافت؛ گزارش نهایی آماده است.')->send();
        }
    }

    private function guard(?ProductImport $import = null): void
    {
        abort_unless(auth()->check(), 403);
        app(ProductImportService::class)->authorize(auth()->user(), $import);
    }

    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()->danger()->title('اطلاعات نیاز به بررسی دارد')
            ->body(e(implode('؛ ', Arr::flatten($exception->errors()))))->persistent()->send();
        parent::onValidationError($exception);
    }
}
