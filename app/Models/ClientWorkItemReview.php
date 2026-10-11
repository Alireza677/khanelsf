<?php

namespace App\Models;

use App\Models\Concerns\ImmutableDeliveryRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientWorkItemReview extends Model
{
    use ImmutableDeliveryRecord;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ClientWorkItemDelivery::class, 'client_work_item_delivery_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
