<?php

namespace App\Models;

use App\Services\ClientProjectCycleDelivery;
use App\Services\ServiceAgreementData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

class ClientProjectPlanVersion extends Model
{
    protected $fillable = ['client_project_id', 'service_offering_version_id', 'version', 'effective_from', 'catalog_snapshot', 'agreement_snapshot', 'override_reason', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'effective_from' => 'date', 'catalog_snapshot' => 'array', 'agreement_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            $source = ServiceOfferingVersion::findOrFail($plan->service_offering_version_id);
            if (! $source->isPublished() || $plan->catalog_snapshot !== $source->catalog_snapshot) {
                throw ValidationException::withMessages(['catalog_snapshot' => 'قرارداد باید از نسخه منتشرشده و snapshot اصلی آن ساخته شود.']);
            }
            $latest = $plan->project?->planVersions()->orderByDesc('version')->first();
            ServiceAgreementData::validate($plan->agreement_snapshot, $latest?->agreement_snapshot['deliverables'] ?? []);
            validator([...$plan->getAttributes(), 'effective_from' => $plan->effective_from?->toDateString()], [
                'client_project_id' => ['required', 'exists:client_projects,id'],
                'version' => ['required', 'integer', 'min:1'],
                'effective_from' => ['required', 'date_format:Y-m-d'],
            ])->validate();
            $featuresChanged = $latest && ServiceAgreementData::featuresForComparison($latest->agreement_snapshot['features'] ?? []) !== ServiceAgreementData::featuresForComparison($plan->agreement_snapshot['features'] ?? []);
            app(ClientProjectCycleDelivery::class)->assertAmendmentDate($plan->project, $plan->effective_from->toDateString(), $featuresChanged);
        });
        static::updating(fn () => throw new LogicException('Customer agreements are immutable; create a new version.'));
        static::deleting(fn () => throw new LogicException('Customer agreements are retained for history.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function offeringVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceOfferingVersion::class, 'service_offering_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
