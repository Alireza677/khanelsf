<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Service;
use App\Support\PersianDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ProjectGalleryFilterService
{
    private const TEXT_FIELDS = ['type' => 'project_type', 'location' => 'location'];

    /** Stable, URL-safe identifiers for existing free-text fields; no new taxonomy. */
    public function textId(string $value): string
    {
        return hash('sha256', $value);
    }

    /** @return array<string, array<int, string>> */
    public function normalize(array $input): array
    {
        $filters = [];

        foreach (['type', 'location', 'service', 'year'] as $key) {
            $values = $input[$key] ?? [];
            $filters[$key] = collect(is_array($values) ? $values : [$values])
                ->filter(fn (mixed $value): bool => is_string($value) || is_int($value))
                ->map(fn ($value): string => (string) $value)
                ->filter(fn (string $value): bool => match ($key) {
                    'type', 'location' => preg_match('/^[a-f0-9]{64}$/D', $value) === 1,
                    'service' => preg_match('/^[1-9][0-9]{0,17}$/D', $value) === 1,
                    'year' => preg_match('/^1[0-9]{3}$/D', $value) === 1,
                })
                ->unique()->take(50)->values()->all();
        }

        return $filters;
    }

    public function query(array $filters): Builder
    {
        $query = Project::query()->published();

        foreach (self::TEXT_FIELDS as $key => $field) {
            if ($filters[$key] !== []) {
                $values = $this->textValues($field)
                    ->filter(fn (string $value): bool => in_array($this->textId($value), $filters[$key], true));
                $query->whereIn($field, $values->all());
            }
        }

        if ($filters['service'] !== []) {
            $query->whereHas('relatedServices', fn (Builder $services): Builder => $services
                ->published()->whereIn('services.id', $filters['service']));
        }

        if ($filters['year'] !== []) {
            $query->where(function (Builder $years) use ($filters): void {
                foreach ($filters['year'] as $year) {
                    $start = PersianDate::toGregorianDate($year.'/01/01');
                    $end = PersianDate::toGregorianDate(((int) $year + 1).'/01/01');
                    $years->orWhere(fn (Builder $range): Builder => $range
                        ->where('project_date', '>=', $start)->where('project_date', '<', $end));
                }
            });
        }

        return $query;
    }

    /** Options come only from published projects; project records are never sent for counting. */
    public function groups(): array
    {
        $groups = [];
        foreach (self::TEXT_FIELDS as $key => $field) {
            $groups[$key] = [
                'label' => $key === 'type' ? 'نوع پروژه' : 'موقعیت پروژه',
                'options' => $this->textValues($field)->map(fn (string $value): array => [
                    'id' => $this->textId($value), 'label' => $value,
                ])->values()->all(),
            ];
        }

        $groups['service'] = [
            'label' => 'خدمات اجراشده',
            'options' => Service::query()->published()
                ->whereHas('projects', fn (Builder $query): Builder => $query->published())
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Service $service): array => ['id' => (string) $service->id, 'label' => $service->name])->all(),
        ];
        $groups['year'] = [
            'label' => 'سال اجرا',
            'options' => Project::query()->published()->whereNotNull('project_date')
                ->distinct()->pluck('project_date')
                ->map(fn ($date): string => PersianDate::latinDigits(PersianDate::year($date)))
                ->unique()->sortDesc()->values()
                ->map(fn (string $year): array => ['id' => $year, 'label' => PersianDate::digits($year)])->all(),
        ];

        return $groups;
    }

    private function textValues(string $field): Collection
    {
        return Project::query()->published()->whereNotNull($field)->where($field, '!=', '')
            ->distinct()->orderBy($field)->pluck($field)->filter(fn (string $value): bool => trim($value) !== '');
    }
}
