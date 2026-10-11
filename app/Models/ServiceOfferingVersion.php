<?php

namespace App\Models;

use App\Services\ServiceAgreementData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class ServiceOfferingVersion extends Model
{
    protected $fillable = ['service_offering_id', 'version', 'delivery_mode', 'schedule_mode', 'billing_basis', 'billing_interval', 'amount', 'currency_code', 'contract_terms', 'scheduling_defaults'];

    protected $attributes = ['status' => 'draft', 'delivery_mode' => 'time', 'schedule_mode' => 'recurring', 'billing_basis' => 'package', 'billing_interval' => 'monthly', 'currency_code' => 'IRT'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'amount' => 'decimal:4', 'scheduling_defaults' => 'array', 'catalog_snapshot' => 'array', 'published_at' => 'datetime'];
    }

    public function setAmountAttribute(mixed $value): void
    {
        $this->attributes['amount'] = ServiceAgreementData::decimal($value, 'amount');
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $storedOfferingId = $this->exists ? self::query()->whereKey($this)->value('service_offering_id') : null;
            ServiceOffering::query()->whereIn('id', array_filter([$storedOfferingId, $this->service_offering_id]))
                ->orderBy('id')->lockForUpdate()->get();

            return parent::save($options);
        }, 3);
    }

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if ($version->exists && self::query()->whereKey($version)->value('status') === 'published') {
                throw new LogicException('Published offering versions are immutable.');
            }
            if (! in_array($version->status, ['draft', 'published'], true)) {
                throw ValidationException::withMessages(['status' => 'وضعیت نسخه معتبر نیست.']);
            }
            ServiceAgreementData::validate([...$version->attributesToArray(), 'deliverables' => []]);
            validator($version->getAttributes(), [
                'service_offering_id' => ['required', 'exists:service_offerings,id'],
                'version' => ['required', 'integer', 'min:1'],
            ])->validate();
            if ($version->status === 'published' && (! $version->published_at || ! $version->catalog_snapshot)) {
                throw new LogicException('Publish offering versions through ServiceOfferingPublisher.');
            }
        });
        static::deleting(function (self $version): void {
            throw new LogicException('Offering versions are retained for contract history.');
        });
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(ServiceOffering::class, 'service_offering_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(ServiceOfferingDeliverable::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function definition(): array
    {
        return ServiceAgreementData::canonical([
            'delivery_mode' => $this->delivery_mode,
            'schedule_mode' => $this->schedule_mode,
            'billing_basis' => $this->billing_basis,
            'billing_interval' => $this->billing_interval,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'contract_terms' => $this->contract_terms,
            'scheduling_defaults' => $this->scheduling_defaults,
            'deliverables' => $this->deliverables()->get()->map->attributesToArray()->all(),
        ]);
    }
}
