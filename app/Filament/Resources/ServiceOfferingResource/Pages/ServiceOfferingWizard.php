<?php

namespace App\Filament\Resources\ServiceOfferingResource\Pages;

use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceOfferingResource\Forms\OfferingWizard;
use App\Models\ServiceOffering;
use App\Services\ServiceOfferingWizardManager;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

abstract class ServiceOfferingWizard extends Page
{
    protected static string $resource = ServiceOfferingResource::class;

    protected static string $view = 'filament.service-offerings.wizard';

    protected static ?string $title = 'راهنمای بسته خدمات';

    public ?array $data = [];

    #[Locked]
    public string $editorToken;

    #[Locked]
    public ?int $offeringId = null;

    public function mount(int|string|null $record = null): void
    {
        $offering = $record ? ServiceOfferingResource::resolveRecordRouteBinding($record) : null;
        abort_if($record && ! $offering, 404);
        abort_unless($offering ? ServiceOfferingResource::canUseWizard($offering) : ServiceOfferingResource::canCreate(), 403);
        $editor = app(ServiceOfferingWizardManager::class)->open(auth()->user(), $offering);
        $this->editorToken = $editor['token'];
        $this->offeringId = $offering?->id;
        if (! $offering) {
            $editor['data']['service_id'] = request()->integer('service') ?: null;
        }
        $this->form->fill($editor['data']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([OfferingWizard::make()])->statePath('data')->model(ServiceOffering::class);
    }

    public function saveDraft(): void
    {
        $this->persist(false);
    }

    public function publish(): void
    {
        $this->persist(true);
    }

    // Keep the former CreateRecord submission method usable by existing integrations.
    public function create(): void
    {
        $this->saveDraft();
    }

    private function persist(bool $publish): void
    {
        $this->resetErrorBag();
        try {
            $version = app(ServiceOfferingWizardManager::class)->save(auth()->user(), $this->editorToken, $this->form->getState(), $publish);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[str_starts_with($field, 'data.') ? $field : 'data.'.$field] = $messages;
            }
            $this->setErrorBag($errors);
            Notification::make()->danger()->title('اطلاعات نیاز به اصلاح دارد')->body('خطاهای هر مرحله در بالای فرم نمایش داده شده‌اند؛ اطلاعات واردشده حفظ شده است.')->send();

            return;
        } catch (LockTimeoutException) {
            Notification::make()->warning()->title('ذخیره قبلی هنوز در حال انجام است')->body('چند لحظه بعد دوباره تلاش کنید. اطلاعات فرم حفظ شده است.')->send();

            return;
        }
        $this->offeringId = $version->service_offering_id;
        Notification::make()->success()->title($version->isPublished() ? 'بسته منتشر شد' : 'پیش‌نویس ذخیره شد')->send();
        if ($version->isPublished()) {
            $this->redirect(ServiceOfferingResource::getUrl('view', ['record' => $this->offeringId]));
        }
    }

    public function groupedErrors(): array
    {
        $groups = [];
        foreach ($this->getErrorBag()->messages() as $field => $messages) {
            $step = OfferingWizard::errorStep($field);
            $groups[$step] = [...($groups[$step] ?? []), ...$messages];
        }

        return $groups;
    }
}
