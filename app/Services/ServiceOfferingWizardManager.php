<?php

namespace App\Services;

use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Coordinates the guided editor; the catalog models and publisher own domain rules. */
final class ServiceOfferingWizardManager
{
    private const METADATA = ['service_id', 'name', 'description', 'sort_order'];

    public function open(User $actor, ?ServiceOffering $offering = null): array
    {
        $this->authorize($actor, $offering);
        $published = $offering?->versions()->where('status', 'published')->first();
        $draft = $offering?->versions()->where('status', 'draft')->when($published, fn ($query) => $query->where('version', '>', $published->version))->first();
        $token = (string) Str::uuid();
        Cache::put($this->key($actor, $token), [
            'offering_id' => $offering?->id,
            'version_id' => $draft?->id,
            'source_id' => $published?->id,
            'fingerprint' => null,
        ], now()->addDay());

        return [
            'token' => $token,
            'data' => [
                'service_id' => null, 'name' => null, 'description' => null, 'sort_order' => 0,
                'delivery_mode' => 'time', 'schedule_mode' => 'recurring',
                'billing_basis' => 'package', 'billing_interval' => 'monthly',
                'amount' => null, 'currency_code' => 'IRT', 'contract_terms' => null,
                'features' => [], 'deliverables' => [], 'scheduling_defaults' => ['cycle_anchor_day' => 1],
                ...($offering ? Arr::only($offering->attributesToArray(), self::METADATA) : []),
                ...(($draft ?? $published)?->definition(false) ?? []),
            ],
        ];
    }

    public function save(User $actor, string $token, array $data, bool $publish = false): ServiceOfferingVersion
    {
        // A server-side receipt binds each editor to one package/version, including retries
        // from an older Livewire snapshot. Expired editors fail rather than creating duplicates.
        $key = $this->key($actor, $token);

        return Cache::lock($key.':lock', 60)->block(3, function () use ($actor, $data, $publish, $key): ServiceOfferingVersion {
            $context = Cache::get($key);
            if (! is_array($context)) {
                throw ValidationException::withMessages(['review' => 'اعتبار این فرم پایان یافته است. اطلاعات را نگه دارید و راهنما را در تب جدید باز کنید.']);
            }
            $offering = isset($context['offering_id']) ? ServiceOffering::findOrFail($context['offering_id']) : null;
            $this->authorize($actor, $offering);
            $metadata = validator(Arr::only($data, self::METADATA), [
                'service_id' => ['required', 'integer', 'exists:services,id'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'sort_order' => ['required', 'integer', 'min:0'],
            ])->validate();
            $definition = ServiceAgreementData::canonical($data);
            $fingerprint = hash('sha256', json_encode([$metadata, $definition], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            $version = DB::transaction(function () use ($actor, $offering, $context, $metadata, $definition, $fingerprint, $publish): ServiceOfferingVersion {
                if ($offering) {
                    $offering = ServiceOffering::whereKey($offering)->lockForUpdate()->firstOrFail();
                    $this->authorize($actor, $offering);
                }
                $version = isset($context['version_id']) ? ServiceOfferingVersion::whereKey($context['version_id'])->lockForUpdate()->firstOrFail() : null;
                if ($version?->isPublished()) {
                    if ($context['fingerprint'] === $fingerprint) {
                        return $version; // Successful publication replay; never update it.
                    }
                    throw ValidationException::withMessages(['review' => 'این نسخه منتشر شده و قفل است. برای تغییر آن از صفحه بسته، نسخه جدید ایجاد کنید.']);
                }
                if (! $version && $offering) {
                    $published = $offering->versions()->where('status', 'published')->first();
                    $pending = $offering->versions()->where('status', 'draft')->when($published, fn ($query) => $query->where('version', '>', $published->version))->exists();
                    if ($pending || $published?->id !== $context['source_id']) {
                        throw ValidationException::withMessages(['review' => 'نسخه دیگری در این فاصله ایجاد شده است. راهنما را از صفحه بسته باز کنید تا ادامه همان نسخه انجام شود.']);
                    }
                }
                $offering ??= ServiceOffering::create($metadata);
                $offering->update([...$metadata, ...($publish ? ['status' => 'active'] : [])]);
                $version ??= app(ServiceOfferingPublisher::class)->newDraft($offering, $actor, $definition);
                Gate::forUser($actor)->authorize('update', $version);

                // Avoid rewriting definition rows for an identical draft retry.
                if ($version->definition(false) !== $definition) {
                    $version->update(Arr::except($definition, ['deliverables']));
                    foreach ($version->deliverables()->get() as $line) {
                        $line->delete();
                    }
                    foreach ($definition['deliverables'] as $index => $line) {
                        $version->deliverables()->create([...$line, 'sort_order' => $index]);
                    }
                }

                return $publish ? app(ServiceOfferingPublisher::class)->publish($version, $actor) : $version->refresh();
            }, 3);

            Cache::put($key, [...$context, 'offering_id' => $version->service_offering_id, 'version_id' => $version->id, 'fingerprint' => $fingerprint], now()->addDay());

            return $version;
        });
    }

    private function key(User $actor, string $token): string
    {
        return 'service-offering-wizard:'.$actor->id.':'.$token;
    }

    private function authorize(User $actor, ?ServiceOffering $offering): void
    {
        Gate::forUser($actor)->authorize($offering ? 'update' : 'create', $offering ?? ServiceOffering::class);
        if ($offering?->status === 'archived') {
            throw ValidationException::withMessages(['review' => 'بسته بایگانی‌شده فقط قابل مشاهده است.']);
        }
    }
}
