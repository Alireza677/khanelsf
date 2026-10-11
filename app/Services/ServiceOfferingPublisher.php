<?php

namespace App\Services;

use App\Models\DeliverableType;
use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ServiceOfferingPublisher
{
    public function newDraft(ServiceOffering $offering, User $actor): ServiceOfferingVersion
    {
        Gate::forUser($actor)->authorize('update', $offering);

        return DB::transaction(function () use ($offering): ServiceOfferingVersion {
            $offering = ServiceOffering::query()->whereKey($offering)->lockForUpdate()->firstOrFail();
            $source = $offering->versions()->first();
            $definition = $source?->definition();
            $version = $offering->versions()->create([
                ...($definition ? array_diff_key($definition, ['deliverables' => true]) : []),
                'version' => ((int) $offering->versions()->max('version')) + 1,
            ]);
            foreach ($definition['deliverables'] ?? [] as $index => $line) {
                $version->deliverables()->create([...$line, 'sort_order' => $index]);
            }

            return $version;
        }, 3);
    }

    public function publish(ServiceOfferingVersion $version, User $actor): ServiceOfferingVersion
    {
        Gate::forUser($actor)->authorize('update', $version);

        return DB::transaction(function () use ($version, $actor): ServiceOfferingVersion {
            // Draft creation and publication share the offering lock.
            ServiceOffering::query()->whereKey($version->service_offering_id)->lockForUpdate()->firstOrFail();
            $version = ServiceOfferingVersion::query()->with('offering.service')->whereKey($version)->lockForUpdate()->firstOrFail();
            if ($version->isPublished()) {
                throw ValidationException::withMessages(['status' => 'این نسخه قبلاً منتشر شده است.']);
            }
            $definition = $version->definition();
            if ($version->delivery_mode !== 'time' && $definition['deliverables'] === []) {
                throw ValidationException::withMessages(['deliverables' => 'برای بسته وظیفه‌محور یا ترکیبی، تعهد تحویل تعریف کنید.']);
            }
            $types = DeliverableType::query()->whereIn('id', array_column($definition['deliverables'], 'deliverable_type_id'))->get()->keyBy('id');
            foreach ($definition['deliverables'] as &$line) {
                $type = $types[$line['deliverable_type_id']];
                if (! $type->active) {
                    throw ValidationException::withMessages(['deliverables' => 'نوع تحویل غیرفعال قابل انتشار نیست.']);
                }
                $line['type_snapshot'] = ['code' => $type->code, 'name' => $type->name, 'description' => $type->description];
            }
            unset($line);
            $version->forceFill([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $actor->id,
                'catalog_snapshot' => [
                    'schema_version' => 1,
                    'offering' => ['id' => $version->offering->id, 'code' => $version->offering->code, 'name' => $version->offering->name, 'description' => $version->offering->description],
                    'service' => ['id' => $version->offering->service_id, 'name' => $version->offering->service->name],
                    'offering_version' => $version->version,
                    ...$definition,
                ],
            ])->save();

            return $version->refresh();
        }, 3);
    }
}
