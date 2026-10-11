<?php

namespace App\Services;

use App\Enums\ClientProjectWorkItemStatus;
use App\Models\ClientProject;
use App\Models\ClientProjectCycle;
use App\Models\ClientProjectWorkItem;
use App\Models\User;
use App\Support\PersianDate;

/** Explicit customer-safe projections. Never serialize event payloads or storage paths. */
final class ClientDeliveryPresenter
{
    public function project(ClientProject $project, ?int $cycleId = null): array
    {
        $cycles = $project->cycles()->whereIn('delivery_mode', ['tasks', 'hybrid'])->orderByDesc('starts_at')->get();
        $cycle = $cycleId ? $cycles->firstWhere('id', $cycleId) : ($cycles->first(fn ($row) => $row->containsDate(today())) ?? $cycles->first());
        abort_if($cycleId && ! $cycle, 404);

        return ['cycles' => $cycles->map(fn ($row) => ['id' => $row->id, 'start' => PersianDate::date($row->starts_at), 'end' => PersianDate::date($row->ends_at)])->all(),
            'cycle' => $cycle ? $this->cycle($cycle) : null];
    }

    public function cycle(ClientProjectCycle $cycle): array
    {
        return ['id' => $cycle->id, 'start' => PersianDate::date($cycle->starts_at), 'end' => PersianDate::date($cycle->ends_at),
            'agreement' => $this->agreement($cycle), 'commitments' => app(ClientProjectDeliveryProgress::class)->cycle($cycle),
            'items' => $cycle->workItems()->where('visibility', 'client')->orderBy('due_at')->get()->map(fn ($item) => $this->item($item))->all(),
            'reports' => $cycle->reports()->where('status', 'published')->get()->map(fn ($report) => ['id' => $report->id, 'version' => $report->version, 'date' => PersianDate::dateTime($report->published_at), 'outcome' => $report->outcome, 'snapshot' => $report->snapshot])->all()];
    }

    public function agreement(ClientProjectCycle $cycle): array
    {
        // The cycle snapshot, never the mutable current catalog, is the display source.
        $snapshot = $cycle->agreement_snapshot ?? [];

        return array_intersect_key($snapshot, array_flip(['service', 'offering', 'amount', 'currency_code', 'contract_terms', 'billing_basis', 'billing_interval', 'schedule_mode', 'delivery_mode']));
    }

    public function item(ClientProjectWorkItem $item, ?User $viewer = null): array
    {
        $deliveries = $item->deliveries()->where('visibility', 'client')->with(['review', 'files' => fn ($q) => $q->where('visibility', 'client')])->get();
        $latest = $item->deliveries()->max('version');
        $revisions = $deliveries->filter(fn ($delivery) => $delivery->review?->decision === 'revision_requested')->count();
        $owner = $viewer && app(ClientDeliveryAccess::class)->allows($viewer, $item, true);

        return ['id' => $item->id, 'title' => $item->title, 'description' => $item->description, 'quantity' => $item->quantity, 'unit' => $item->unit,
            'status' => $item->status->value, 'status_label' => ClientProjectWorkItemStatus::options()[$item->status->value],
            'due' => PersianDate::dateTime($item->due_at), 'scheduled_start' => PersianDate::dateTime($item->scheduled_start_at), 'scheduled_end' => PersianDate::dateTime($item->scheduled_end_at),
            'assignees' => $item->assignees()->pluck('name')->all(), 'lock_version' => $item->lock_version,
            'deliveries' => $deliveries->map(fn ($delivery) => ['id' => $delivery->id, 'version' => $delivery->version, 'message' => $delivery->message,
                'date' => PersianDate::dateTime($delivery->submitted_at), 'review_state' => $delivery->review_state,
                'review_reason' => $delivery->review?->reason, 'review_date' => PersianDate::dateTime($delivery->review?->reviewed_at),
                'can_review' => $owner && $delivery->version === (int) $latest && $item->status->value === 'submitted' && $delivery->review_state === 'pending',
                'can_request_revision' => $revisions < $delivery->revision_allowance,
                'files' => $delivery->files->map(fn ($file) => ['id' => $file->id, 'name' => $file->original_name, 'size' => $file->size])->all()])->all(),
            'timeline' => $item->events()->where('visibility', 'client')->whereIn('to_status', ['planned', 'ready', 'in_progress', 'submitted', 'revision_requested', 'accepted', 'completed'])
                ->where(fn ($q) => $q->whereNull('from_status')->orWhereColumn('from_status', '!=', 'to_status')->orWhere('kind', 'scheduled'))->orderBy('id')->get()
                ->map(fn ($event) => ['label' => $event->kind === 'scheduled' ? 'برنامه کار ثبت شد' : ClientProjectWorkItemStatus::options()[$event->to_status], 'date' => PersianDate::dateTime($event->created_at)])->all()];
    }
}
