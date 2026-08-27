<?php

namespace App\Services;

use App\Models\Invoice;

final class InvoicePartySnapshot
{
    public function capture(Invoice $i): array
    {
        $c = $i->customer;
        $s = app(SettingsService::class);

        return [
            'customer_name_snapshot' => $c?->display_name, 'customer_company_snapshot' => $c?->company_name, 'customer_mobile_snapshot' => $c?->mobile, 'customer_email_snapshot' => $c?->email, 'customer_address_snapshot' => $c?->address,
            'issuer_name_snapshot' => $s->siteName(), 'issuer_phone_snapshot' => $s->contactPhone(), 'issuer_email_snapshot' => $s->contactEmail(), 'issuer_address_snapshot' => $s->contactAddress(), 'issuer_website_snapshot' => config('app.url')];
    }
}
