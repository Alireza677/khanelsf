<?php

namespace Tests\Fixtures;

use App\CMS\Blocks\Hero\HeroBlock;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HeroV2EditorLifecycleComponent extends Component implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    public ?array $data = [];

    public array $saved = [];

    public bool $nestedUpdateHandled = false;

    public function mount(array $blocks): void
    {
        $this->form->fill(['blocks' => $blocks]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Builder::make('blocks')->blocks([
                app(HeroBlock::class)->filamentBlock(HeroBlock::CONTEXT_PAGE),
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
