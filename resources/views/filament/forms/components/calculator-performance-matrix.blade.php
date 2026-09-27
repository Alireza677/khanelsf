@php
    $criteria = $getCriteria();
    $results = $getResults();
    $cells = collect($getChildComponentContainer()->getComponents())->keyBy(fn ($cell) => $cell->getName());
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div dir="rtl" class="calculator-performance-matrix" style="min-width: 0; max-width: 100%;">
        @if ($criteria === [] || $results === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">برای تکمیل ماتریس، ابتدا حداقل یک معیار و یک نتیجه اضافه کنید.</p>
        @else
            <div style="overflow-x: auto; max-width: 100%;" tabindex="0" role="region" aria-label="جدول امتیاز عملکرد نتایج در معیارها">
                <table class="w-full text-sm" style="border-collapse: separate; border-spacing: 0.5rem;">
                    <caption class="sr-only">امتیاز عملکرد هر نتیجه در هر معیار؛ از صفر تا پنج</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="min-width: 10rem; text-align: right;">معیار</th>
                            @foreach ($results as $key => $label)
                                <th scope="col" style="min-width: 6rem; max-width: 12rem;" wire:key="{{ $getStatePath() }}-heading-{{ $key }}">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($criteria as $id => $label)
                            <tr wire:key="{{ $getStatePath() }}-row-{{ $id }}">
                                <th scope="row" style="text-align: right; font-weight: 500;">{{ $label }}</th>
                                @foreach ($results as $key => $resultLabel)
                                    <td style="min-width: 6rem; vertical-align: top;" wire:key="{{ $getStatePath() }}-cell-{{ $id }}-{{ $key }}">
                                        {{ $cells->get("{$key}.{$id}") }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">خانه‌های خالی معادل صفر هستند. برای دیدن همهٔ نتایج، جدول را به‌صورت افقی جابه‌جا کنید.</p>
        @endif
    </div>
</x-dynamic-component>
