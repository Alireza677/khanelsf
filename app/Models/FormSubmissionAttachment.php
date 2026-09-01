<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionAttachment extends Model
{
    protected $fillable = [
        'form_submission_id',
        'field_key',
        'stored_path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }
}
