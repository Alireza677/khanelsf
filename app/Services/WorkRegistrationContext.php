<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectCycle;
use App\Models\ClientProjectWorkItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Resolves registration context from the project and its historical cycle, never form prices. */
final class WorkRegistrationContext
{
    public function project(mixed $id): ?ClientProject
    {
        $project = app(ActivityWizardProjectContext::class)->find($id);
        if ($project) {
            Gate::authorize('update', $project);
        }

        return $project;
    }

    public function choices(mixed $id): array
    {
        return match ($this->project($id)?->delivery_mode) {
            'tasks' => ['work_item' => 'ثبت کار / تحویل خروجی'],
            'hybrid' => ['time' => 'ثبت فعالیت زمانی', 'work_item' => 'ثبت کار / تحویل خروجی'],
            default => ['time' => 'ثبت فعالیت زمانی'],
        };
    }

    public function operation(mixed $id, ?string $operation): string
    {
        $choices = $this->choices($id);
        $operation ??= array_key_first($choices);
        if (! array_key_exists($operation, $choices)) {
            throw ValidationException::withMessages(['operation' => 'این عملیات برای پروژه انتخاب‌شده مجاز نیست.']);
        }

        return $operation;
    }

    public function cycle(mixed $id): ?ClientProjectCycle
    {
        $project = $this->project($id);

        return $project && $project->delivery_mode !== 'time'
            ? $project->cycles()->containingDate(today())->oldest('starts_at')->first() : null;
    }

    public function item(mixed $projectId, mixed $itemId): ?ClientProjectWorkItem
    {
        return $itemId ? $this->cycle($projectId)?->workItems()->with('commitment')->find($itemId) : null;
    }

    public function itemOptions(mixed $projectId): array
    {
        return $this->cycle($projectId)?->workItems()->orderBy('due_at')->orderBy('id')->get()
            ->mapWithKeys(fn ($item) => [$item->id => $item->title.' — '.$item->status->options()[$item->status->value]])->all() ?? [];
    }

    public function canEdit(?ClientProjectWorkItem $item): bool
    {
        return $item && ! in_array($item->status->value, ['completed', 'cancelled'], true)
            && ! ($item->status->value === 'submitted' && $item->approvalRequired());
    }

    public function canDeliver(?ClientProjectWorkItem $item): bool
    {
        return $item && ($item->status->value === 'in_progress' || ($item->status->value === 'submitted' && ! $item->deliveries()->exists()));
    }
}
