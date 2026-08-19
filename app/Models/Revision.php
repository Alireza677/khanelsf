<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'revision_number',
        'created_by',
        'snapshot',
        'checksum',
        'event',
        'restored_from_revision_id',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_revision_id');
    }
}
