<?php

namespace App\Models;

use App\Contracts\VideoProviderAdapter;
use App\Models\Concerns\HasFeaturedImage;
use App\Rules\VideoRelatedUrl;
use App\Services\Video\VideoDuration;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Video extends Model implements HasMedia
{
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $fillable = [
        'title', 'slug', 'excerpt', 'description', 'source_url',
        'duration_seconds', 'related_url', 'status', 'published_at', 'sort_order',
    ];

    protected $attributes = ['provider' => 'aparat', 'status' => 'draft', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'duration_seconds' => 'integer', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $video): void {
            $provider = app(VideoProviderAdapter::class);
            $source = $provider->parseUrl((string) $video->source_url);
            if ($source === null || $video->provider !== 'aparat') {
                throw ValidationException::withMessages(['source_url' => 'لینک HTTPS معتبر ویدئو در aparat.com/v/... را وارد کنید.']);
            }
            $video->forceFill($source);
            if (! $video->exists && blank($video->slug)) {
                $video->slug = Str::slug($video->title) ?: Str::lower((string) Str::ulid());
            }

            Validator::make($video->getAttributes(), [
                'title' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('videos', 'slug')->ignore($video->getKey())],
                'excerpt' => ['nullable', 'string', 'max:2000'],
                'description' => ['nullable', 'string'],
                'external_id' => [Rule::unique('videos', 'external_id')->where('provider', $video->provider)->ignore($video->getKey())],
                'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:'.VideoDuration::MAX_SECONDS],
                'related_url' => ['nullable', 'string', 'max:2048', new VideoRelatedUrl],
                'status' => ['required', Rule::in(['draft', 'published'])],
                'published_at' => ['nullable', 'date'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            ], ['external_id.unique' => 'این ویدئوی آپارات قبلاً در کتابخانه ثبت شده است.'])->validate();

            if ($video->isDirty('description') && $video->description !== null) {
                $video->description = (string) RichText::render($video->description);
            }
            if (! $video->exists || $video->isDirty('external_id')) {
                $video->thumbnail_url = null;
                $video->applyMetadata($provider->fetchMetadata($video->external_id));
            }
            $video->thumbnail_url = $provider->trustedThumbnailUrl($video->thumbnail_url);
        });
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(VideoCategory::class)->orderBy('video_categories.sort_order')->orderBy('name');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where(fn (Builder $query) => $query
            ->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function embedUrl(): ?string
    {
        return $this->provider === 'aparat' ? app(VideoProviderAdapter::class)->embedUrl((string) $this->external_id) : null;
    }

    public function thumbnailUrl(): string
    {
        return $this->featuredImageUrl('thumb')
            ?: app(VideoProviderAdapter::class)->trustedThumbnailUrl($this->thumbnail_url)
            ?: asset('images/video-placeholder.svg');
    }

    public function formattedDuration(): ?string
    {
        return VideoDuration::format($this->duration_seconds);
    }

    public function refreshMetadata(): bool
    {
        $metadata = app(VideoProviderAdapter::class)->fetchMetadata((string) $this->external_id);
        if ($metadata === []) {
            return false;
        }
        $this->applyMetadata($metadata);
        $this->save();

        return true;
    }

    private function applyMetadata(array $metadata): void
    {
        if (isset($metadata['thumbnail_url'])) {
            $this->thumbnail_url = $metadata['thumbnail_url'];
        }
        if ($this->duration_seconds === null && isset($metadata['duration_seconds'])) {
            $this->duration_seconds = $metadata['duration_seconds'];
        }
    }
}
