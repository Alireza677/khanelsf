<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'source',
        'page_id',
        'page_url',
        'block_id',
        'payload',
        'calculation_result',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'calculation_result' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }
}
