<?php

namespace App\Models;

use App\Services\ExternalVideoResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProjectVideo extends Model
{
    protected $fillable = [
        'url',
        'provider',
        'title',
        'caption',
        'thumbnail_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $video): void {
            $resolved = app(ExternalVideoResolver::class)->resolve((string) $video->url);

            if (! app(ExternalVideoResolver::class)->isSafeExternalUrl($video->url)) {
                throw ValidationException::withMessages([
                    'url' => 'نشانی ویدئو باید یک نشانی معتبر HTTP یا HTTPS باشد.',
                ]);
            }

            $video->url = $resolved['external_url'];
            $video->provider = $resolved['provider'];
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function embedUrl(): ?string
    {
        return app(ExternalVideoResolver::class)->resolve($this->url)['embed_url'];
    }

    public function isEmbeddable(): bool
    {
        return $this->embedUrl() !== null;
    }
}
