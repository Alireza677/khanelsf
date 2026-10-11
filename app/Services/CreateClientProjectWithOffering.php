<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\Customer;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateClientProjectWithOffering
{
    public function create(array $attributes, int $versionId, array $overrides, ?string $reason, User $actor): ClientProject
    {
        Gate::forUser($actor)->authorize('create', ClientProject::class);

        return DB::transaction(function () use ($attributes, $versionId, $overrides, $reason, $actor): ClientProject {
            $version = ServiceOfferingVersion::findOrFail($versionId);
            $customer = Customer::findOrFail($attributes['customer_id']);
            if (! $customer->isActive()) {
                throw ValidationException::withMessages(['customer_id' => 'مشتری فعال را انتخاب کنید.']);
            }
            $project = ClientProject::create([...$attributes, 'delivery_mode' => $version->delivery_mode]);
            app(ClientProjectAgreementManager::class)->append($project, $version, $overrides, $reason, $project->start_date?->toDateString() ?? today()->toDateString(), $actor);
            if ($project->supportsOperationalCycles()) {
                app(ClientProjectCycleReconciler::class)->reconcile($project, true);
            }

            return $project;
        }, 3);
    }
}
