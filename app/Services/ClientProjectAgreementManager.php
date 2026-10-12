<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectPlanVersion;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ClientProjectAgreementManager
{
    public function append(ClientProject $project, ServiceOfferingVersion $version, array $overrides, ?string $reason, string $effectiveFrom, User $actor): ClientProjectPlanVersion
    {
        Gate::forUser($actor)->authorize('update', $project);

        return DB::transaction(function () use ($project, $version, $overrides, $reason, $effectiveFrom, $actor): ClientProjectPlanVersion {
            $project = ClientProject::query()->whereKey($project)->lockForUpdate()->firstOrFail();
            $version = ServiceOfferingVersion::query()->with('offering')->whereKey($version)->firstOrFail();
            $latest = $project->planVersions()->orderByDesc('version')->first();
            if (! $version->isPublished() || ($version->offering->status !== 'active' && $latest?->service_offering_version_id !== $version->id)) {
                throw ValidationException::withMessages(['offering_version_id' => 'نسخه منتشرشده یک بسته فعال را انتخاب کنید.']);
            }
            if ($version->delivery_mode !== $project->delivery_mode || $version->schedule_mode !== $project->schedule_mode) {
                throw ValidationException::withMessages(['offering_version_id' => 'نوع تحویل و زمان‌بندی قرارداد باید با پروژه یکسان باشد.']);
            }
            validator(['effective_from' => $effectiveFrom], ['effective_from' => ['required', 'date_format:Y-m-d']])->validate();
            if ($latest && (CarbonImmutable::parse($effectiveFrom)->lt(today()) || $effectiveFrom < $latest->effective_from->toDateString())) {
                throw ValidationException::withMessages(['effective_from' => 'اصلاحیه نباید پیش از امروز یا آخرین قرارداد مؤثر شود.']);
            }
            $catalog = $version->catalog_snapshot;
            $allowed = ServiceAgreementData::OVERRIDE_FIELDS;
            if (array_diff(array_keys($overrides), $allowed) !== []) {
                throw ValidationException::withMessages(['contract' => 'فیلد اصلاحیه معتبر نیست.']);
            }
            $base = ServiceAgreementData::canonical($catalog, $catalog['deliverables']);
            // An unrelated amendment of the same package must retain customer feature overrides.
            if ($latest?->service_offering_version_id === $version->id && ! array_key_exists('features', $overrides)) {
                $overrides['features'] = $latest->agreement_snapshot['features'] ?? [];
            }
            $agreed = ServiceAgreementData::canonical([...$base, ...$overrides], $latest?->agreement_snapshot['deliverables'] ?? []);
            $schedule = self::projectSchedule($project);
            $defaultsChanged = collect($base['scheduling_defaults'])->contains(fn ($value, $key) => ($schedule[$key] ?? null) != $value);
            if (($latest || $agreed !== $base || $defaultsChanged) && blank($reason)) {
                throw ValidationException::withMessages(['contract_override_reason' => 'دلیل تغییر شرایط قرارداد را ثبت کنید.']);
            }
            // Types are resolved from the published snapshot, never today's catalog.
            $typeSnapshots = collect($catalog['deliverables'])->keyBy('deliverable_type_id');
            foreach ($agreed['deliverables'] as &$line) {
                if (! isset($typeSnapshots[$line['deliverable_type_id']])) {
                    throw ValidationException::withMessages(['deliverables' => 'برای نوع تحویل جدید، ابتدا نسخه جدید بسته را منتشر کنید.']);
                }
                $line['type_snapshot'] = $typeSnapshots[$line['deliverable_type_id']]['type_snapshot'];
            }
            unset($line);

            return $project->planVersions()->create([
                'service_offering_version_id' => $version->id,
                'version' => ($latest?->version ?? 0) + 1,
                'effective_from' => $effectiveFrom,
                'catalog_snapshot' => $catalog,
                'agreement_snapshot' => [
                    ...$catalog,
                    ...$agreed,
                    'schema_version' => ServiceAgreementData::SCHEMA_VERSION,
                    'project_schedule' => $schedule,
                ],
                'override_reason' => filled($reason) ? trim($reason) : null,
                'created_by' => $actor->id,
            ]);
        }, 3);
    }

    public function updateProject(ClientProject $project, array $attributes, ?string $reason, User $actor): ClientProject
    {
        Gate::forUser($actor)->authorize('update', $project);

        return DB::transaction(function () use ($project, $attributes, $reason, $actor): ClientProject {
            $project = ClientProject::query()->whereKey($project)->lockForUpdate()->firstOrFail();
            $latest = $project->planVersions()->orderByDesc('version')->first();
            $before = self::projectSchedule($project);
            $updated = app(ClientProjectCycleReconciler::class)->updateProject($project, $attributes);
            if ($latest && $before !== self::projectSchedule($updated)) {
                $overrides = array_intersect_key($latest->agreement_snapshot, array_flip(ServiceAgreementData::OVERRIDE_FIELDS));
                $this->append($updated, $latest->offeringVersion, $overrides, $reason, today()->toDateString(), $actor);
            }

            return $updated;
        }, 3);
    }

    public static function projectSchedule(ClientProject $project): array
    {
        return [
            'schedule_mode' => $project->schedule_mode,
            'cycle_anchor_day' => $project->cycle_anchor_day,
            'monthly_hour_limit_minutes' => $project->monthly_hour_limit_minutes,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
        ];
    }
}
