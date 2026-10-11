<?php

namespace App\Services;

use App\Models\ClientProjectCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ClientProjectWorkItemGenerator
{
    public function generate(ClientProjectCycle $cycle, User $actor): int
    {
        Gate::forUser($actor)->authorize('update', $cycle->project);

        return DB::transaction(function () use ($cycle, $actor): int {
            $cycle = ClientProjectCycle::query()->whereKey($cycle)->lockForUpdate()->firstOrFail();
            $created = 0;
            foreach ($cycle->commitments()->orderBy('definition_index')->get() as $line) {
                // Each whole unit is individually manageable; a fractional remainder is one item.
                $whole = bcdiv($line->quantity, '1', 0);
                $fraction = bcsub($line->quantity, $whole, 4);
                if (bccomp($whole, '1000', 0) > 0 || ($whole === '1000' && bccomp($fraction, '0', 4) > 0)) {
                    throw ValidationException::withMessages(['quantity' => 'تولید خودکار بیش از ۱۰۰۰ کار برای یک تعهد مجاز نیست.']);
                }
                $count = (int) $whole + (bccomp($fraction, '0', 4) > 0 ? 1 : 0);
                for ($index = 1; $index <= $count; $index++) {
                    $item = $cycle->workItems()->firstOrCreate(['generation_key' => 'commitment:'.$line->id.':'.$index], [
                        'client_project_id' => $cycle->client_project_id, 'client_project_cycle_commitment_id' => $line->id,
                        'title' => $line->name.' — '.$index, 'description' => $line->description,
                        'quantity' => $index <= (int) $whole ? '1.0000' : $fraction, 'unit' => $line->unit,
                        'due_at' => isset($line->scheduling_rules['due_day_offset']) ? $cycle->starts_at->copy()->addDays($line->scheduling_rules['due_day_offset'])->endOfDay() : $cycle->ends_at->copy()->subDay()->endOfDay(),
                    ]);
                    if ($item->wasRecentlyCreated) {
                        $created++;
                        $item->events()->create(['actor_id' => $actor->id, 'to_status' => 'planned', 'lock_version' => 0, 'created_at' => now()]);
                    }
                }
            }
            app(ClientProjectDeliveryProgress::class)->refresh($cycle);

            return $created;
        }, 3);
    }
}
