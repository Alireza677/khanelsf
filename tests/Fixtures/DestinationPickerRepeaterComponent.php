<?php

namespace Tests\Fixtures;

use App\CMS\Actions\Filament\ActionPicker;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DestinationPickerRepeaterComponent extends Component implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    public ?array $data = [];

    public array $saved = [];

    public function mount(array $data = []): void
    {
        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            ActionPicker::make('primary'),
            ActionPicker::make('secondary'),
            Forms\Components\Repeater::make('cards')->cloneable()->reorderable()->schema([
                ActionPicker::make('action'),
            ]),
        ])->statePath('data');
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
