<?php

namespace Tests\Fixtures;

use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BuilderLifecycleComponent extends Component implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    public ?array $data = [];

    public array $saved = [];

    public bool $nestedUpdateHandled = false;

    public function mount(array $blocks = []): void
    {
        $this->form->fill(['blocks' => $blocks]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([$this->builder()])->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): View
    {
        return view('testing.builder-lifecycle');
    }

    protected function builder(): Builder
    {
        return Builder::make('blocks')->cloneable()->blocks([
            Block::make('hero')->schema([
                Forms\Components\TextInput::make('content.title'),
                Forms\Components\TextInput::make('content.lead'),
                Forms\Components\Textarea::make('content.description'),
                Forms\Components\TextInput::make('content.primary_cta.label'),
                Forms\Components\TextInput::make('content.primary_cta.url'),
                Forms\Components\Select::make('settings.alignment')->options(['start' => 'Start', 'end' => 'End']),
                Forms\Components\Select::make('settings.background_treatment')
                    ->options(['image' => 'Image', 'paths' => 'Paths'])
                    ->live()
                    ->afterStateUpdated(function ($livewire): void {
                        $livewire->nestedUpdateHandled = true;
                        $livewire->skipRender();
                    }),
                Forms\Components\Repeater::make('content.stats')->schema([
                    Forms\Components\TextInput::make('value'),
                    Forms\Components\TextInput::make('label'),
                    Forms\Components\TextInput::make('description'),
                    Forms\Components\TextInput::make('icon'),
                    Forms\Components\TextInput::make('icon_size')->numeric(),
                ]),
                Forms\Components\Repeater::make('content.selector.items')->schema([
                    Forms\Components\TextInput::make('label'),
                    Forms\Components\TextInput::make('url'),
                ]),
                Forms\Components\Repeater::make('content.social_links')->schema([
                    Forms\Components\TextInput::make('label'),
                    Forms\Components\TextInput::make('url'),
                ]),
                Forms\Components\ViewField::make('content.media.url')
                    ->view('filament.forms.components.media-library-url-picker')
                    ->viewData(['images' => []]),
                Forms\Components\TextInput::make('settings.height.desktop')
                    ->afterStateHydrated(fn (Get $get, Set $set) => $set('settings.height.desktop', $get('settings.height.desktop'))),
                Forms\Components\TextInput::make('settings.background_effect.type'),
                Forms\Components\TextInput::make('settings.background_effect.speed'),
            ]),
        ]);
    }
}
