<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = [
        'source_path',
        'target_url',
        'status_code',
        'is_active',
        'hits_count',
        'last_hit_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hits_count' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function setSourcePathAttribute(string $value): void
    {
        $this->attributes['source_path'] = self::normalizePath($value);
    }

    public static function normalizePath(string $path): string
    {
        $path = parse_url(trim($path), PHP_URL_PATH) ?: trim($path);
        $path = '/'.ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function targetPath(): ?string
    {
        if (str_starts_with($this->target_url, 'http://') || str_starts_with($this->target_url, 'https://')) {
            return parse_url($this->target_url, PHP_URL_PATH) ?: '/';
        }

        return self::normalizePath($this->target_url);
    }

    public function pointsToItself(): bool
    {
        return $this->targetPath() === $this->source_path;
    }

    public function recordHit(): void
    {
        $this->forceFill([
            'hits_count' => $this->hits_count + 1,
            'last_hit_at' => now(),
        ])->save();
    }
}
