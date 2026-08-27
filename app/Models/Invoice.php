<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Invoice extends Model
{
    protected $fillable = ['customer_id', 'client_project_cycle_id', 'invoice_number', 'period_start', 'period_end', 'currency', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'status', 'issued_at', 'due_at', 'paid_at', 'notes', 'internal_notes', 'created_by', 'customer_name_snapshot', 'customer_company_snapshot', 'customer_mobile_snapshot', 'customer_email_snapshot', 'customer_address_snapshot', 'issuer_name_snapshot', 'issuer_phone_snapshot', 'issuer_email_snapshot', 'issuer_address_snapshot', 'issuer_website_snapshot', 'pdf_disk', 'pdf_path', 'pdf_hash', 'pdf_status', 'pdf_error', 'pdf_generated_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'status' => InvoiceStatus::class, 'issued_at' => 'datetime', 'due_at' => 'datetime', 'paid_at' => 'datetime', 'pdf_generated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $invoice): void {
            if ($invoice->getRawOriginal('status') !== InvoiceStatus::Draft->value) {
                $allowed = ['status', 'paid_at', 'pdf_disk', 'pdf_path', 'pdf_hash', 'pdf_status', 'pdf_error', 'pdf_generated_at', 'updated_at'];
                if (array_diff(array_keys($invoice->getDirty()), $allowed) !== []) {
                    throw new LogicException('Issued invoices are immutable.');
                }
            }
        });
        static::deleting(fn (self $invoice) => $invoice->status !== InvoiceStatus::Draft
            ? throw new LogicException('Only draft invoices may be deleted.') : null);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ClientProjectCycle::class, 'client_project_cycle_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
