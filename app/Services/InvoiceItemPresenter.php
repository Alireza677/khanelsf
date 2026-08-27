<?php

namespace App\Services;

use App\Models\InvoiceItem;

final class InvoiceItemPresenter
{
    public function commercialLine(InvoiceItem $item): string
    {
        $price = $item->unit_price === null ? 'بدون قیمت واحد' : number_format((float) $item->unit_price).' '.$item->currency;

        return match ($item->pricing_mode_snapshot) {
            'hourly' => app(DurationFormatter::class)->format((int) $item->duration_minutes_snapshot).' × '.$price,
            'per_unit' => ($item->quantity ?? '—').' '.($item->service_unit_label_snapshot ?: $item->service_unit_snapshot ?: 'واحد').' × '.$price,
            'fixed' => 'قیمت ثابت: '.$price,
            default => $price,
        };
    }
}
