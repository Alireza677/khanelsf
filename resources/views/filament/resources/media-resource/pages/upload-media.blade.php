<x-filament-panels::page>
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="save"
    >
        {{ $this->form }}

        <x-filament::button type="submit" form="form">
            بارگذاری
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
