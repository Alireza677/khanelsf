<?php

namespace App\Models;

use App\Models\Concerns\ImmutableDeliveryRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCycleReport extends Model
{
    use ImmutableDeliveryRecord;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array', 'published_at' => 'datetime', 'version' => 'integer'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ClientProjectCycle::class, 'client_project_cycle_id');
    }
}
