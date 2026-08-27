<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'client_project_activity_id', 'client_project_id', 'service_id', 'project_title_snapshot', 'activity_title_snapshot', 'service_name_snapshot', 'service_unit_snapshot', 'service_unit_label_snapshot', 'pricing_mode_snapshot', 'description_snapshot', 'duration_minutes_snapshot', 'quantity', 'unit_price', 'total_amount', 'currency', 'activity_date_snapshot'];

    protected function casts(): array
    {
        return ['duration_minutes_snapshot' => 'integer', 'quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'total_amount' => 'decimal:2', 'activity_date_snapshot' => 'date'];
    }

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            $status = $item->invoice()->value('status');
            $status = $status instanceof InvoiceStatus ? $status : InvoiceStatus::tryFrom((string) $status);
            if ($status !== InvoiceStatus::Draft) {
                throw new LogicException('Issued invoice items are immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
        static::deleted(function (self $item): void {
            if ($item->client_project_activity_id) {
                DB::table('invoice_activity_claims')->where('client_project_activity_id', $item->client_project_activity_id)->where('invoice_id', $item->invoice_id)->delete();
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClientProjectActivity::class, 'client_project_activity_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
