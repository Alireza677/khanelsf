<?php

namespace App\Services;

use App\Models\Redirect;

class RedirectCsvExporter
{
    public function headings(): array
    {
        return [
            'source_path',
            'target_url',
            'status_code',
            'is_active',
            'hits_count',
            'last_hit_at',
            'updated_at',
        ];
    }

    public function row(Redirect $redirect): array
    {
        return [
            $redirect->source_path,
            $redirect->target_url,
            $redirect->status_code,
            $redirect->is_active ? '1' : '0',
            $redirect->hits_count,
            $redirect->last_hit_at?->toDateTimeString(),
            $redirect->updated_at?->toDateTimeString(),
        ];
    }
}
