<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    public function attachments(): HasMany
    {
        return $this->hasMany(FormSubmissionAttachment::class);
    }

    /** @return Collection<string, Collection<int, FormSubmissionAttachment>> */
    public function attachmentsByField(): Collection
    {
        return $this->attachments->groupBy('field_key');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $submission): void {
            $paths = $submission->attachments()->pluck('stored_path')->all();

            DB::afterCommit(fn () => Storage::disk('local')->delete($paths));
        });
    }
}
