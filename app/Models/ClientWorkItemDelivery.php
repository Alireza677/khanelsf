<?php

namespace App\Models;

use App\Models\Concerns\ImmutableDeliveryRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClientWorkItemDelivery extends Model
{
    use ImmutableDeliveryRecord;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['submitted_at' => 'datetime', 'approval_required' => 'boolean', 'version' => 'integer', 'revision_allowance' => 'integer'];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(ClientProjectWorkItem::class, 'client_project_work_item_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClientDeliveryFile::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(ClientWorkItemReview::class);
    }

    public function getReviewStateAttribute(): string
    {
        return $this->review?->decision ?? ($this->approval_required ? 'pending' : 'not_required');
    }
}
