<?php

namespace App\Models;

use App\Enums\ClientProjectWorkItemStatus;
use App\Services\ServiceAgreementData;
use App\Support\ServiceDeliveryNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use LogicException;

class ClientProjectWorkItem extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'planned', 'visibility' => 'internal', 'lock_version' => 0];

    protected function casts(): array
    {
        return ['status' => ClientProjectWorkItemStatus::class, 'quantity' => 'decimal:4', 'lock_version' => 'integer', 'scheduled_start_at' => 'datetime', 'scheduled_end_at' => 'datetime', 'due_at' => 'datetime', 'started_at' => 'datetime', 'operational_completed_at' => 'datetime', 'accepted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function setQuantityAttribute(mixed $value): void
    {
        $this->attributes['quantity'] = ServiceAgreementData::decimal($value, 'quantity');
    }

    protected static function booted(): void
    {
        // All edits/transitions must use the manager's locked compare-and-swap path.
        static::updating(fn () => throw new LogicException('Use ClientProjectWorkItemManager to update work items.'));
        static::deleting(fn () => throw new LogicException('Cancel work items instead of deleting history.'));
        static::creating(function (self $item): void {
            $cycle = ClientProjectCycle::findOrFail($item->client_project_cycle_id);
            if ($cycle->delivery_mode === 'time' || (int) $cycle->client_project_id !== (int) $item->client_project_id || $item->status !== ClientProjectWorkItemStatus::Planned) {
                throw ValidationException::withMessages(['client_project_cycle_id' => 'دوره تحویل و پروژه باید یکسان باشند.']);
            }
            if ($item->client_project_cycle_commitment_id) {
                $commitment = ClientProjectCycleCommitment::findOrFail($item->client_project_cycle_commitment_id);
                if ((int) $commitment->client_project_cycle_id !== (int) $cycle->id || $item->unit !== $commitment->unit || bccomp($item->quantity ?? '0', '0', 4) <= 0) {
                    throw ValidationException::withMessages(['quantity' => 'مقدار و واحد کار باید با تعهد دوره سازگار باشند.']);
                }
                // Keep the generator's fractional remainder from a historical snapshot
                // usable, while new integral commitments cannot acquire fractional work.
                $remainder = bcsub($commitment->quantity, bcdiv($commitment->quantity, '1', 0), 4);
                if (ServiceDeliveryNumber::quantityFormat($item->unit) !== 'integer' || bccomp($remainder, '0', 4) === 0 || bccomp($item->quantity, $remainder, 4) !== 0) {
                    ServiceAgreementData::quantity($item->quantity, $item->unit);
                }
            } else {
                ServiceAgreementData::quantity($item->quantity, $item->unit);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ClientProjectCycle::class, 'client_project_cycle_id');
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(ClientProjectCycleCommitment::class, 'client_project_cycle_commitment_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_project_work_item_user');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(ClientProjectActivity::class, 'client_project_work_item_activity');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClientProjectWorkItemEvent::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ClientWorkItemDelivery::class)->orderBy('version');
    }

    public function approvalRequired(): bool
    {
        return (bool) $this->commitment?->approval_required;
    }
}
