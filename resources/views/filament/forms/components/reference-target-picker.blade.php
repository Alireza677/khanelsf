@php
    $statePath = $getStatePath();
    $target = $getTargetType();
    $configuration = '{ state: $wire.'.$applyStateBindingModifiers("\$entangle('{$statePath}')").', target: '.\Illuminate\Support\Js::from($target).', label: '.\Illuminate\Support\Js::from($getSelectedLabel()).', endpoint: '.\Illuminate\Support\Js::from(route('admin.internal-links.search')).', disabled: '.($isDisabled() ? 'true' : 'false').' }';
    $browserAttributes = new \Illuminate\View\ComponentAttributeBag([
        'wire:ignore' => true,
        'wire:key' => $this->getId().'.'.$statePath.'.destination.'.$target.'.'.(int) $isDisabled(),
    ]);
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament.forms.components.destination-browser', compact('configuration', 'browserAttributes'))
</x-dynamic-component>
