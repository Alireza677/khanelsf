<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    use HasFactory;

    public const SCHEMA_VERSION = 2;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'display_mode',
        'type',
        'calculator_identifier',
        'schema_version',
        'schema',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'schema' => 'array',
            'settings' => 'array',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isCalculator(): bool
    {
        return $this->type === 'calculator';
    }
}
