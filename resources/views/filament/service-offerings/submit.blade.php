<div class="flex flex-wrap gap-3">
    <x-filament::button type="button" color="gray" wire:click="saveDraft"
        wire:loading.attr="disabled" wire:target="saveDraft,publish,create">
        ذخیره پیش‌نویس
    </x-filament::button>
    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveDraft,publish,create">
        انتشار بسته
    </x-filament::button>
</div>
