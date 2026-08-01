<?php

namespace Tests\Fixtures;

use App\CMS\Actions\Filament\ActionPicker;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ActionPickerLifecycleComponent extends Component implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    public ?array $data = [];

    public array $saved = [];

    public bool $required = false;

    public function mount(?array $action = null, bool $required = false): void
    {
        $this->required = $required;
        $this->form->fill(['action' => $action]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                ActionPicker::make('action')
                    ->label('مقصد آزمایشی')
                    ->required(fn (): bool => $this->required),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): View
    {
        return view('testing.builder-lifecycle');
    }
}
