<?php

namespace App\Models;

use App\Models\Concerns\ImmutableDeliveryRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDeliveryFile extends Model
{
    use ImmutableDeliveryRecord;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['disk', 'path'];

    protected $casts = ['uploaded_at' => 'datetime', 'size' => 'integer'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ClientWorkItemDelivery::class, 'client_work_item_delivery_id');
    }
}
