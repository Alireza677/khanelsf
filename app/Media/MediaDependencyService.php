<?php

namespace App\Media;

use App\Filament\Resources\MediaResource;
use App\Models\Media;
use App\Models\MediaUsage;
use App\Models\Template;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MediaDependencyService
{
    public array $cleanupFailures = [];

    private array $modalReports = [];

    public function __construct(private readonly MediaConsumerRegistry $consumers, private readonly BlockMediaReferences $blocks) {}

    /** Internal inventory includes inaccessible and invalid rows so neither can be silently discarded. */
    public function inventory(Media $source, bool $includeOwned = true, bool $lock = false): array
    {
        $rows = [];
        $usageQuery = MediaUsage::query()->where('media_id', $source->id)->orderBy('id');
        if ($lock) {
            $usageQuery->lockForUpdate();
        }
        foreach ($usageQuery->get() as $usage) {
            $class = array_search($usage->usable_type, array_map(fn ($model) => (new $model)->getMorphClass(), array_keys($this->consumers->all())), true);
            $models = array_keys($this->consumers->all());
            $owner = $class !== false ? $models[$class]::query()->find($usage->usable_id) : null;
            $supported = $owner && in_array($usage->collection_name, ['featured_image', 'gallery'], true)
                && method_exists($owner, 'mediaUsages');
            $rows[] = $this->row($owner, 'usage', (string) $usage->id,
                match ($usage->collection_name) {
                    'featured_image' => 'تصویر شاخص', 'gallery' => 'گالری تصاویر', default => 'نوع استفاده ثبت‌نشده'
                },
                (bool) $supported, $usage->getAttributes(), ['usage_id' => $usage->id, 'expected_order' => $usage->order_column]);
        }
        foreach ($this->consumers->all() as $model => $definition) {
            $query = $model::query()->orderBy((new $model)->getKeyName());
            if ($lock) {
                $query->lockForUpdate();
            }
            foreach ($query->lazy(100) as $owner) {
                if ($definition['blocks']) {
                    foreach ($this->blocks->inspect($owner->blocks ?? [], $source) as $reference) {
                        $rows[] = $this->row($owner, 'block', $reference['path'], $reference['label'], $reference['supported'], $reference['identity'], $reference);
                    }
                }
                foreach ($definition['fields'] as $field) {
                    foreach ($this->blocks->manualReferences($owner->getAttribute($field), $source) as $path => $value) {
                        $rows[] = $this->row($owner, 'manual', "$field.$path", match ($field) {
                            'seo_image' => 'تصویر شبکه‌های اجتماعی',
                            'thumbnail_url', 'cached_thumbnail' => 'تصویر ویدئو',
                            'schema', 'settings' => 'تصویر در تنظیمات فرم',
                            'value' => 'تنظیمات سایت',
                            default => 'تصویر یا لینک در محتوای متنی',
                        }, false, $value);
                    }
                }
                if ($includeOwned && $source->model_type === $owner->getMorphClass() && (int) $source->model_id === (int) $owner->id && $source->collection_name !== 'media_library') {
                    $used = match ($source->collection_name) {
                        'featured_image' => method_exists($owner, 'featuredImage') && $owner->featuredImage()?->id === $source->id,
                        'gallery' => method_exists($owner, 'galleryImages') && $owner->galleryImages()->contains('id', $source->id),
                        'images' => method_exists($owner, 'images') && $owner->images()->contains('id', $source->id),
                        default => false,
                    };
                    if ($used && ! collect($rows)->contains(fn ($row) => $row['kind'] === 'usage' && $row['owner']?->is($owner))) {
                        $rows[] = $this->row($owner, 'manual', 'owned:'.$source->collection_name, 'تصویر اختصاصی قدیمی؛ از فرم محتوا تغییر دهید.', false, $source->id);
                    }
                }
            }
        }

        return $rows;
    }

    private function row(?Model $owner, string $kind, string $location, string $label, bool $supported, mixed $snapshot, array $extra = []): array
    {
        return [
            ...$extra, 'owner' => $owner, 'kind' => $kind, 'location' => $location, 'label' => $label, 'supported' => $supported,
            'key' => hash('sha256', json_encode([$owner?->getMorphClass(), $owner?->getKey(), $kind, $location, $snapshot])),
        ];
    }

    public function report(Media $source): array
    {
        $this->authorizeMedia($source);

        return array_map(function (array $row): array {
            $owner = $row['owner'];
            $readable = $owner && $this->consumers->readable($owner);

            return [
                'key' => $row['key'],
                'title' => $readable ? ($this->consumers->accessOwner($owner)->title ?: $owner->name ?: $owner->getAttribute('key') ?: '#'.$owner->getKey()) : 'محل استفاده با دسترسی محدود یا ارجاع نامعتبر',
                'type' => $readable ? $this->consumers->resource($owner)::getModelLabel() : 'نیازمند بررسی مجاز',
                'label' => $readable ? $row['label'] : 'جزئیات برای این کاربر قابل نمایش نیست.',
                'replaceable' => $readable && $row['supported'] && $this->consumers->editable($owner),
                'shared_template' => $readable && $owner instanceof Template,
                'status' => ! $owner ? 'unresolved' : ($readable ? 'identified' : 'restricted'),
                'diagnostic' => ! $owner && isset($row['usage_id'])
                    ? 'ارجاع ثبت‌شده شماره '.$row['usage_id'].' فاقد Consumer قابل تشخیص است؛ پاک‌سازی خودکار انجام نمی‌شود.' : null,
                ...($readable ? $this->consumers->links($owner) : ['edit_url' => null, 'public_url' => null]),
            ];
        }, $this->inventory($source));
    }

    public function modalReport(Media $source): array
    {
        $this->authorizeMedia($source);

        return $this->modalReports[auth()->id().':'.$source->id] ??= $this->report($source);
    }

    public function assertUnused(Media $source, bool $includeOwned = true, bool $lock = false): void
    {
        if ($this->inventory($source, $includeOwned, $lock) !== []) {
            throw new MediaInUseException;
        }
    }

    public function authorizeMedia(Media $source): void
    {
        abort_unless(auth()->user()?->isAdmin() && auth()->user()?->isActive()
            && MediaResource::canAccess() && MediaResource::canView($source) && MediaResource::canDelete($source), 403);
    }

    /** Selective replacement is atomic. A requested delete is part of the same database transaction. */
    public function apply(Media $source, ?int $replacementId, array $keys, string $operation): void
    {
        $this->authorizeMedia($source);
        $this->cleanupFailures = [];
        $this->modalReports = [];
        if (! in_array($operation, ['replace', 'replace_delete', 'delete'], true)) {
            $this->invalid('عملیات نامعتبر است.');
        }
        DB::transaction(function () use ($source, $replacementId, $keys, $operation): void {
            $locked = Media::query()->whereIn('id', array_filter([$source->id, $replacementId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked->get($source->id);
            if (! $source) {
                $this->invalid('رسانه قبلاً حذف شده است؛ فهرست را تازه‌سازی کنید.');
            }
            $this->authorizeMedia($source);
            if ($operation !== 'delete') {
                $replacement = $locked->get($replacementId);
                if (! $replacement || $replacement->id === $source->id || ! $replacement->isReusableImage()) {
                    $this->invalid('یک تصویر جایگزین معتبر و متفاوت از کتابخانه انتخاب کنید.');
                }
                abort_unless(MediaResource::canView($replacement), 403);
                $inventory = collect($this->inventory($source))->keyBy('key');
                $keys = array_values(array_unique($keys));
                if ($keys === []) {
                    $this->invalid('حداقل یک محل استفاده را انتخاب کنید.');
                }
                $resolved = [];
                $owners = [];
                foreach ($keys as $key) {
                    $row = $inventory->get($key);
                    if (! $row || ! $row['supported'] || ! $row['owner']) {
                        $this->invalid('محل استفاده تغییر کرده یا جایگزینی خودکار آن پشتیبانی نمی‌شود؛ مودال را دوباره باز کنید.');
                    }
                    $ownerKey = $row['owner']::class.':'.$row['owner']->getKey();
                    $owner = $owners[$ownerKey] ??= $row['owner']::query()->whereKey($row['owner']->getKey())->lockForUpdate()->first();
                    abort_unless($owner && $this->consumers->editable($owner), 403);
                    // Locking reads deliberately bypass a repeatable-read inventory snapshot.
                    $usage = null;
                    if ($row['kind'] === 'usage') {
                        $usage = MediaUsage::query()->whereKey($row['usage_id'])->lockForUpdate()->first();
                        $freshKey = $usage ? $this->row($owner, 'usage', (string) $usage->id, $row['label'], true, $usage->getAttributes())['key'] : null;
                    } else {
                        $freshKey = collect($this->blocks->inspect($owner->blocks ?? [], $source))
                            ->map(fn ($reference) => $this->row($owner, 'block', $reference['path'], $reference['label'], $reference['supported'], $reference['identity'])['key'])
                            ->first(fn ($current) => $current === $key);
                    }
                    if ($freshKey !== $key) {
                        $this->invalid('ارجاع هم‌زمان تغییر کرده است؛ هیچ تغییری اعمال نشد.');
                    }
                    $resolved[] = [$row, $owner, $usage];
                }
                foreach ($resolved as [$row, $owner, $usage]) {
                    if ($row['kind'] === 'usage') {
                        if ($owner->mediaUsages()->where('collection_name', $usage->collection_name)->where('media_id', $replacement->id)->whereKeyNot($usage->id)->exists()) {
                            $this->invalid('تصویر جایگزین از قبل در همین گالری وجود دارد؛ برای حفظ ترتیب تصویر دیگری انتخاب کنید.');
                        }
                        $usage->update(['media_id' => $replacement->id]);
                    } else {
                        $blocks = $owner->blocks;
                        data_set($blocks, $row['path'], $replacement->id);
                        if ($row['paired_path']) {
                            data_set($blocks, $row['paired_path'], $replacement->getUrl());
                        }
                        $owner->blocks = $blocks;
                        $owner->save();
                    }
                }
                foreach ($resolved as [$row, $owner]) {
                    $owner = $owner::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
                    abort_unless($this->consumers->editable($owner), 403);
                    $valid = $row['kind'] === 'usage'
                        ? MediaUsage::query()->whereKey($row['usage_id'])->where('media_id', $replacement->id)->where('order_column', $row['expected_order'])->lockForUpdate()->first() !== null
                        : ((int) data_get($owner->blocks, $row['path']) === (int) $replacement->id
                            && (! $row['paired_path'] || data_get($owner->blocks, $row['paired_path']) === $replacement->getUrl()));
                    if (! $valid) {
                        $this->invalid('وضعیت ارجاع پیش از پایان عملیات تغییر کرد؛ تغییرات بازگردانده شد.');
                    }
                }
            }
            $this->authorizeMedia($source);
            if ($operation !== 'replace') {
                $this->assertUnused($source, lock: true);
                $source->delete();
            }
        }, 3);
    }

    public function deleteMany(array $ids): void
    {
        $this->cleanupFailures = [];
        DB::transaction(function () use ($ids): void {
            $records = Media::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            foreach ($records as $record) {
                $this->authorizeMedia($record);
                $this->assertUnused($record, lock: true);
            }
            foreach ($records as $record) {
                $record->delete();
            }
        }, 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }
}
