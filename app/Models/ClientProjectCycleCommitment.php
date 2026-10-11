<?php

namespace App\Models;

use App\Services\ServiceAgreementData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ClientProjectCycleCommitment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'approval_required' => 'boolean', 'revision_allowance' => 'integer', 'scheduling_rules' => 'array', 'definition_snapshot' => 'array'];
    }

    public function setQuantityAttribute(mixed $value): void
    {
        $this->attributes['quantity'] = ServiceAgreementData::decimal($value, 'quantity');
    }

    protected static function booted(): void
    {
        static::creating(function (self $commitment): void {
            $cycle = ClientProjectCycle::findOrFail($commitment->client_project_cycle_id);
            $line = $cycle->agreement_snapshot['deliverables'][$commitment->definition_index] ?? null;
            if (! $line || (int) $cycle->client_project_plan_version_id !== (int) $commitment->client_project_plan_version_id || $line !== $commitment->definition_snapshot) {
                throw new LogicException('Commitments must use the cycle agreement snapshot.');
            }
            foreach (['deliverable_type_id', 'name', 'description', 'quantity', 'unit', 'approval_required', 'revision_allowance', 'scheduling_rules'] as $key) {
                $matches = match ($key) {
                    'quantity' => bccomp($commitment->quantity ?? '0', $line['quantity'], 4) === 0,
                    'deliverable_type_id', 'revision_allowance' => (int) $commitment->$key === (int) $line[$key],
                    'approval_required' => $commitment->approval_required === (bool) $line[$key],
                    default => $commitment->$key === ($line[$key] ?? null),
                };
                if (! $matches) {
                    throw new LogicException('Commitment fields must match the definition snapshot.');
                }
            }
        });
        static::updating(fn () => throw new LogicException('Cycle commitments are immutable.'));
        static::deleting(fn () => throw new LogicException('Cycle commitments are retained for history.'));
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ClientProjectCycle::class, 'client_project_cycle_id');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(ClientProjectWorkItem::class);
    }
}
