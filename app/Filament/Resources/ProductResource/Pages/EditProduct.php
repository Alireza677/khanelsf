<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use App\Filament\Resources\ProductResource;
use App\Services\ProductPriceFreshness;
use App\Services\ProductPricingService;
use Filament\Actions;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = ProductResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->priceChangeSource = 'manual';

        return parent::handleRecordUpdate($record, $data);
    }

    protected function afterSave(): void
    {
        ProductResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
        ProductResource::syncMediaLibraryCollection(
            $this->record,
            'gallery',
            data_get($this->form->getRawState(), 'gallery_media_ids', []),
        );
        ProductResource::syncRelatedProducts(
            $this->record,
            data_get($this->form->getRawState(), 'related_products', []),
        );
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'ویرایش محصول';
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()->label('ذخیره تغییرات');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verifyPrice')->label('بررسی شد؛ قیمت تغییری نکرد')->icon('heroicon-o-check-badge')
                ->requiresConfirmation()->modalHeading('تأیید قیمت ذخیره‌شده')
                ->form([
                    Hidden::make('expected_price')->required(),
                    Hidden::make('expected_sale_price'),
                ])
                ->fillForm(fn () => ['expected_price' => $this->record->price, 'expected_sale_price' => $this->record->sale_price])
                ->modalDescription(fn () => 'قیمت اصلی: '.$this->record->price.' تومان؛ فروش ویژه: '.($this->record->sale_price ?? '—').'؛ ابتدا تغییرات فرم را ذخیره کنید.')
                ->action(function (array $data): void {
                    abort_unless(ProductResource::canEdit($this->record), 403);
                    $state = $this->form->getRawState();
                    $pricing = app(ProductPricingService::class);
                    if ($pricing->amount($state['price']) !== $this->record->price
                        || (filled($state['sale_price'] ?? null) ? $pricing->amount($state['sale_price']) : null) !== $this->record->sale_price) {
                        throw ValidationException::withMessages(['price' => 'ابتدا تغییرات قیمت را ذخیره کنید؛ سپس قیمت ثبت‌شده را تأیید کنید.']);
                    }
                    $this->record = app(ProductPriceFreshness::class)->verify($this->record, auth()->user(), $data);
                    Notification::make()->title('قیمت تأیید شد')->success()->send();
                }),
            Actions\Action::make('preview')
                ->label('پیش‌نمایش')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('admin.preview.products.show', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('viewPublic')
                ->label('مشاهده محصول در سایت')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => ProductResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => ProductResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make()->label('حذف محصول'),
        ];
    }
}
