<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_submission_id',
        'form_id',
        'page_id',
        'name',
        'phone',
        'email',
        'notes',
        'calculation_result',
        'status',
        'source',
        'page_url',
        'block_id',
    ];

    protected function casts(): array
    {
        return ['calculation_result' => 'array'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
