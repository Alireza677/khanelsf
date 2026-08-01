<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class ProductMediaService
{
    public function context(Product $product): array
    {
        $product->loadMissing([
            'media',
            'documents',
        ]);

        $featured = $product->getFirstMedia('featured_image');

        return [
            'featured' => $featured ? $this->mediaItem($featured) : null,
            'gallery' => $product->getMedia('gallery')
                ->map(fn (Media $media): array => $this->mediaItem($media))
                ->values(),
            'documents' => $product->documents
                ->map(fn (ProductDocument $document): array => $this->documentItem($document))
                ->values(),
        ];
    }

    public function seoImageUrls(Product $product, ?array $mediaContext = null): array
    {
        $mediaContext ??= $this->context($product);

        return collect([
            $product->seo_image,
            data_get($mediaContext, 'featured.url'),
            ...collect($mediaContext['gallery'] ?? [])->pluck('url')->all(),
        ])
            ->filter(fn (mixed $url): bool => is_string($url) && filled($url))
            ->unique()
            ->values()
            ->all();
    }

    private function mediaItem(Media $media): array
    {
        return [
            'id' => $media->getKey(),
            'name' => $media->name,
            'fileName' => $media->file_name,
            'url' => $media->getUrl(),
            'mimeType' => $media->mime_type,
            'size' => (int) $media->size,
            'sortOrder' => (int) ($media->order_column ?? 0),
        ];
    }

    private function documentItem(ProductDocument $document): array
    {
        return [
            'id' => $document->getKey(),
            'title' => $document->title,
            'url' => $this->documentUrl($document),
            'filePath' => $document->file_path,
            'originalName' => $document->original_name,
            'mimeType' => $document->mime_type,
            'fileSize' => $document->file_size,
            'sortOrder' => (int) $document->sort_order,
        ];
    }

    private function documentUrl(ProductDocument $document): ?string
    {
        if (filled($document->external_url)) {
            return (string) $document->external_url;
        }

        if (blank($document->file_path)) {
            return null;
        }

        try {
            return Storage::disk($document->disk ?: 'public')->url($document->file_path);
        } catch (Throwable) {
            return null;
        }
    }
}
