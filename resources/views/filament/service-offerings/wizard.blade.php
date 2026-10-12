<x-filament-panels::page>
    <div dir="rtl" class="space-y-6">
        @if ($errors->any())
            <x-filament::section heading="اصلاح اطلاعات بسته">
                <div class="space-y-4" role="alert">
                    @foreach ($this->groupedErrors() as $step => $messages)
                        <div>
                            <x-filament::button color="gray" size="sm" type="button"
                                x-on:click="$dispatch('offering-wizard-step', { step: @js($step) })">
                                بازگشت به {{ \App\Filament\Resources\ServiceOfferingResource\Forms\OfferingWizard::STEPS[$step] }}
                            </x-filament::button>
                            <ul class="mt-2 list-inside list-disc text-sm text-danger-600 dark:text-danger-400">
                                @foreach (array_unique($messages) as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
        <form wire:submit="publish">
            {{ $this->form }}
        </form>
        <x-filament::link :href="$offeringId ? \App\Filament\Resources\ServiceOfferingResource::getUrl('view', ['record' => $offeringId]) : \App\Filament\Resources\ServiceOfferingResource::getUrl()">
            {{ $offeringId ? 'مشاهده بسته و تاریخچه' : 'بازگشت به فهرست بسته‌ها' }}
        </x-filament::link>
    </div>
</x-filament-panels::page>
