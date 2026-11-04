<?php

namespace App\Media;

use App\CMS\Navigation\Contracts\ResolvesNavigationUrl;
use App\Filament\Resources;
use App\Models;
use App\Services\ModuleService;
use Illuminate\Database\Eloquent\Model;

/** Domain adapters for the existing media_usages and content contracts, not a second usage store. */
final class MediaConsumerRegistry
{
    private array $consumers = [];

    public function __construct()
    {
        foreach ([
            [Models\Page::class, Resources\PageResource::class, ['content', 'seo_image'], true],
            [Models\Template::class, Resources\TemplateResource::class, [], true],
            [Models\Project::class, Resources\ProjectResource::class, ['content', 'seo_image'], false],
            [Models\Service::class, Resources\ServiceResource::class, ['description', 'content', 'seo_image'], false],
            [Models\Product::class, Resources\ProductResource::class, ['description', 'content', 'seo_image'], false],
            [Models\Post::class, Resources\PostResource::class, ['content', 'seo_image'], false],
            [Models\Video::class, Resources\VideoResource::class, ['description', 'source_url', 'cached_thumbnail'], false],
            [Models\Gallery::class, Resources\GalleryResource::class, ['description', 'seo_image', 'thumbnail_url'], false],
            [Models\Setting::class, Resources\SettingResource::class, ['value'], false],
            [Models\Form::class, Resources\FormResource::class, ['schema', 'settings'], false],
            [Models\Category::class, Resources\CategoryResource::class, ['description'], false],
            [Models\ProjectCategory::class, Resources\ProjectCategoryResource::class, ['description', 'seo_image'], false],
            [Models\ProductCategory::class, Resources\ProductCategoryResource::class, ['description', 'seo_image'], false],
            [Models\GalleryCategory::class, Resources\GalleryCategoryResource::class, ['description', 'seo_image'], false],
        ] as [$model, $resource, $fields, $blocks]) {
            $this->register($model, $resource, $fields, $blocks);
        }
        $this->register(Models\ProjectVideo::class, Resources\ProjectResource::class, ['thumbnail_url'], false, 'project');
    }

    public function register(string $model, string $resource, array $fields = [], bool $blocks = false, ?string $parent = null): void
    {
        $this->consumers[$model] = compact('resource', 'fields', 'blocks', 'parent');
    }

    public function all(): array
    {
        return $this->consumers;
    }

    public function resource(Model $owner): ?string
    {
        return $this->consumers[$owner::class]['resource'] ?? null;
    }

    public function readable(Model $owner): bool
    {
        $resource = $this->resource($owner);
        $owner = $this->accessOwner($owner);

        return $owner && $resource && $resource::canAccess() && $resource::canView($owner);
    }

    public function editable(Model $owner): bool
    {
        $resource = $this->resource($owner);

        return $this->readable($owner) && $resource::canEdit($this->accessOwner($owner));
    }

    public function accessOwner(Model $owner): ?Model
    {
        $parent = $this->consumers[$owner::class]['parent'] ?? null;

        return $parent ? $owner->{$parent} : $owner;
    }

    public function links(Model $owner): array
    {
        if (! $this->readable($owner)) {
            return ['edit_url' => null, 'public_url' => null];
        }

        $resource = $this->resource($owner);
        $owner = $this->accessOwner($owner);
        $edit = $this->editable($owner) && array_key_exists('edit', $resource::getPages())
            ? $resource::getUrl('edit', ['record' => $owner]) : null;
        $published = method_exists($resource, 'isPubliclyVisible')
            ? $resource::isPubliclyVisible($owner)
            : (method_exists($owner, 'isPublished') && $owner->isPublished());
        $public = $published ? ($owner instanceof ResolvesNavigationUrl
            ? $owner->resolveNavigationUrl()
            : (method_exists($resource, 'publicUrl') ? $resource::publicUrl($owner) : null)) : null;
        if ($public && ! app(ModuleService::class)->urlIsVisible($public)) {
            $public = null;
        }

        return ['edit_url' => $edit, 'public_url' => $public];
    }
}
