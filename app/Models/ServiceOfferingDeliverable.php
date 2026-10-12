<?php

namespace App\Models;

use App\Services\ServiceAgreementData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

class ServiceOfferingDeliverable extends Model
{
    protected $fillable = ['service_offering_version_id', 'deliverable_type_id', 'name', 'description', 'quantity', 'unit', 'approval_required', 'revision_allowance', 'scheduling_rules', 'sort_order'];

    protected $attributes = ['approval_required' => false, 'revision_allowance' => 0, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'approval_required' => 'boolean', 'revision_allowance' => 'integer', 'scheduling_rules' => 'array', 'sort_order' => 'integer'];
    }

    public function setQuantityAttribute(mixed $value): void
    {
        $this->attributes['quantity'] = ServiceAgreementData::decimal($value, 'quantity');
    }

    private function lockOfferings(): void
    {
        $storedVersionId = $this->exists ? self::query()->whereKey($this)->value('service_offering_version_id') : null;
        $offeringIds = ServiceOfferingVersion::query()->whereIn('id', array_filter([$storedVersionId, $this->service_offering_version_id]))->pluck('service_offering_id');
        ServiceOffering::query()->whereIn('id', $offeringIds)->orderBy('id')->lockForUpdate()->get();
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $this->lockOfferings();

            return parent::save($options);
        }, 3);
    }

    public function delete(): ?bool
    {
        return DB::transaction(function (): ?bool {
            $this->lockOfferings();

            return parent::delete();
        }, 3);
    }

    protected static function booted(): void
    {
        $guard = function (self $line): void {
            $ids = array_filter([$line->getOriginal('service_offering_version_id'), $line->service_offering_version_id]);
            if (ServiceOfferingVersion::query()->whereIn('id', $ids)->where('status', 'published')->exists()) {
                throw new LogicException('Published deliverable definitions are immutable.');
            }
        };
        static::saving(function (self $line) use ($guard): void {
            $guard($line);
            $parent = ServiceOfferingVersion::findOrFail($line->service_offering_version_id);
            $preserved = $line->exists && ! $line->isDirty(['quantity', 'unit', 'deliverable_type_id']) ? [$line->getOriginal()] : [];
            ServiceAgreementData::validate([...$parent->attributesToArray(), 'deliverables' => [$line->attributesToArray()]], $preserved);
        });
        static::deleting($guard);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ServiceOfferingVersion::class, 'service_offering_version_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DeliverableType::class, 'deliverable_type_id');
    }
}
