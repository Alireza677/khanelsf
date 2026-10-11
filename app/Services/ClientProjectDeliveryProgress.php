<?php

namespace App\Services;

use App\Models\ClientProjectCycle;
use App\Models\ClientProjectCycleCommitment;
use Illuminate\Support\Facades\DB;

final class ClientProjectDeliveryProgress
{
    public function commitment(ClientProjectCycleCommitment $commitment): array
    {
        $completed = '0.0000';
        $operational = '0.0000';
        foreach ($commitment->workItems()->get() as $item) {
            if ($item->status->value === 'cancelled') {
                continue;
            }
            if ($item->operational_completed_at) {
                $operational = bcadd($operational, $item->quantity ?? '0', 4);
            }
            if (in_array($item->status->value, ['accepted', 'completed'], true) && (! $commitment->approval_required || $item->accepted_at)) {
                $completed = bcadd($completed, $item->quantity ?? '0', 4);
            }
        }
        $completed = bccomp($completed, $commitment->quantity, 4) > 0 ? $commitment->quantity : $completed;
        $remaining = bcsub($commitment->quantity, $completed, 4);

        return ['definition_index' => $commitment->definition_index, 'deliverable_type_id' => $commitment->deliverable_type_id, 'name' => $commitment->name, 'unit' => $commitment->unit, 'committed' => $commitment->quantity, 'operational_completed' => $operational, 'completed' => $completed, 'remaining' => $remaining, 'approval_required' => $commitment->approval_required];
    }

    public function cycle(ClientProjectCycle $cycle): array
    {
        return $cycle->commitments()->orderBy('definition_index')->get()->map(fn ($line) => $this->commitment($line))->all();
    }

    public function refresh(ClientProjectCycle $cycle): void
    {
        if ($cycle->delivery_mode === 'time') {
            return;
        }
        $rows = $this->cycle($cycle);
        $fulfilled = $rows !== [] && collect($rows)->every(fn ($row) => bccomp($row['remaining'], '0', 4) === 0);
        $waiting = $cycle->workItems()->where('status', 'submitted')->whereHas('commitment', fn ($query) => $query->where('approval_required', true))->exists();
        $status = $fulfilled ? 'fulfilled' : ($waiting ? 'awaiting_approval' : ($cycle->ends_at->lte(today()) ? 'overdue' : ($cycle->workItems()->exists() ? 'active' : 'planned')));
        // Derived status only; never touch hourly/financial fields.
        DB::table('client_project_cycles')->where('id', $cycle->id)->update(['delivery_status' => $status]);
    }
}
