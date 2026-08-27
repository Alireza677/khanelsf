<?php

namespace App\Services;

use App\Models\ClientProjectActivity;

final class InvoiceItemSnapshot
{
    public function from(ClientProjectActivity $activity): array
    {
        return ['client_project_activity_id' => $activity->getKey(), 'client_project_id' => $activity->client_project_id, 'service_id' => $activity->service_id, 'project_title_snapshot' => $activity->project?->title, 'activity_title_snapshot' => $activity->title, 'service_name_snapshot' => $activity->service_name_snapshot, 'service_unit_snapshot' => $activity->service_unit_snapshot, 'service_unit_label_snapshot' => $activity->service_unit_label_snapshot, 'pricing_mode_snapshot' => $activity->pricing_mode_snapshot, 'description_snapshot' => $activity->description, 'duration_minutes_snapshot' => $activity->duration_minutes, 'quantity' => $activity->quantity, 'unit_price' => $activity->unit_price_snapshot, 'total_amount' => $activity->total_amount, 'currency' => strtoupper($activity->currency_snapshot), 'activity_date_snapshot' => $activity->activity_date];
    }
}
