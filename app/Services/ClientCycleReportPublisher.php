<?php

namespace App\Services;

use App\Models\ClientCycleReport;
use App\Models\ClientProjectCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ClientCycleReportPublisher
{
    public function publish(ClientProjectCycle $cycle, User $actor, string $outcome, ?string $message = null): ClientCycleReport
    {
        Gate::forUser($actor)->authorize('update', $cycle->project);
        validator(compact('outcome', 'message'), ['outcome' => ['required', Rule::in(['successful', 'incomplete'])], 'message' => ['nullable', 'string', 'max:10000']])->validate();

        return DB::transaction(function () use ($cycle, $actor, $outcome, $message) {
            $cycle = ClientProjectCycle::whereKey($cycle)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $cycle->project);
            if ($cycle->delivery_mode === 'time' || $cycle->ends_at->gt(today())) {
                throw ValidationException::withMessages(['cycle' => 'گزارش تحویل برای دوره پایان‌یافته وظیفه‌ای یا ترکیبی منتشر می‌شود.']);
            }
            $presenter = app(ClientDeliveryPresenter::class);
            $progress = app(ClientProjectDeliveryProgress::class)->cycle($cycle);
            $fulfilled = $progress !== [] && collect($progress)->every(fn ($row) => bccomp($row['remaining'], '0', 4) === 0);
            if ($outcome === 'successful' && ! $fulfilled) {
                throw ValidationException::withMessages(['outcome' => 'تعهدات ناقص یا منتظر تأیید هستند؛ گزارش موفق مجاز نیست.']);
            }
            // Store only a public projection; no recursive reports, internal notes or paths.
            $items = $cycle->workItems()->where('visibility', 'client')->orderBy('id')->get()->map(function ($item) use ($presenter) {
                $row = $presenter->item($item);
                unset($row['lock_version']);
                foreach ($row['deliveries'] as &$delivery) {
                    unset($delivery['can_review'], $delivery['can_request_revision']);
                }
                unset($delivery);

                return $row;
            })->all();

            return $cycle->reports()->create(['version' => ($cycle->reports()->max('version') ?? 0) + 1, 'status' => 'published', 'outcome' => $outcome,
                'published_by' => $actor->id, 'published_at' => now(), 'snapshot' => ['schema_version' => 1, 'starts_at' => $cycle->starts_at->toDateString(),
                    'ends_at' => $cycle->ends_at->toDateString(), 'agreement' => $presenter->agreement($cycle), 'commitments' => $progress,
                    'fulfilled' => $fulfilled, 'message' => $message, 'items' => $items]]);
        }, 3);
    }
}
