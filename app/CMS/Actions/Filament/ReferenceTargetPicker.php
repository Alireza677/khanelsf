<?php

namespace App\CMS\Actions\Filament;

use Closure;
use Filament\Forms\Components\Field;

/** Also usable by legacy reference fields (form_id/video_id) without changing their JSON. */
final class ReferenceTargetPicker extends Field
{
    protected string $view = 'filament.forms.components.reference-target-picker';

    private string|Closure $targetType = 'page';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules([
            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

                if ($id === false || ! app(ActionPickerService::class)->referenceIsAvailable($this->getTargetType(), $id)) {
                    $fail('یک مقصد منتشرشده و قابل دسترسی انتخاب کنید.');
                }
            },
        ]);
    }

    public function targetType(string|Closure $type): static
    {
        $this->targetType = $type;

        return $this;
    }

    public function getTargetType(): string
    {
        return (string) $this->evaluate($this->targetType);
    }

    public function getSelectedLabel(): string
    {
        return filled($this->getState())
            ? app(ActionPickerService::class)->selectedOptionLabel($this->getTargetType(), $this->getState())
            : 'موردی انتخاب نشده است';
    }
}
