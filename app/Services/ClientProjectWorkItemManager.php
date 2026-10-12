<?php

namespace App\Services;

use App\Models\ClientProjectCycle;
use App\Models\ClientProjectWorkItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ClientProjectWorkItemManager
{
    private const EDITABLE = ['title', 'description', 'quantity', 'unit', 'visibility', 'scheduled_start_at', 'scheduled_end_at', 'due_at'];

    public function createExtra(ClientProjectCycle $cycle, array $data, User $actor): ClientProjectWorkItem
    {
        Gate::forUser($actor)->authorize('update', $cycle->project);

        return DB::transaction(function () use ($cycle, $data, $actor) {
            $cycle = ClientProjectCycle::query()->whereKey($cycle)->lockForUpdate()->firstOrFail();
            $attributes = ['visibility' => 'internal', ...array_intersect_key($data, array_flip(self::EDITABLE))];
            $this->validate($attributes);
            $item = ClientProjectWorkItem::create([...$attributes, 'client_project_id' => $cycle->client_project_id, 'client_project_cycle_id' => $cycle->id, 'generation_key' => 'manual:'.Str::uuid()]);
            $this->links($item, $data);
            $this->event($item, null, null, $actor);
            app(ClientProjectDeliveryProgress::class)->refresh($cycle);

            return $item;
        }, 3);
    }

    public function update(ClientProjectWorkItem $item, array $data, int $expectedVersion, User $actor): ClientProjectWorkItem
    {
        Gate::forUser($actor)->authorize('update', $item->project);

        return DB::transaction(function () use ($item, $data, $expectedVersion, $actor) {
            // Lock the cycle first: generator, edits and progress share the same order.
            $cycle = ClientProjectCycle::query()->whereKey($item->client_project_cycle_id)->lockForUpdate()->firstOrFail();
            $item = ClientProjectWorkItem::query()->whereKey($item)->lockForUpdate()->firstOrFail();
            if ($item->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['lock_version' => 'این کار تغییر کرده است؛ صفحه را تازه‌سازی کنید.']);
            }
            $attributes = array_intersect_key($data, array_flip(self::EDITABLE));
            // Generated contractual quantities and associations never drift.
            if ($item->client_project_cycle_commitment_id) {
                foreach (['quantity', 'unit'] as $field) {
                    $value = array_key_exists($field, $attributes) ? $attributes[$field] : $item->$field;
                    $same = $field === 'quantity'
                        ? ServiceAgreementData::decimal($value, 'quantity') === $item->quantity
                        : (string) $value === (string) $item->$field;
                    if (array_key_exists($field, $attributes) && ! $same) {
                        throw ValidationException::withMessages([$field => 'مقدار و واحد کار قراردادی ثابت است.']);
                    }
                    unset($attributes[$field]);
                }
            }
            $quantityChanged = array_key_exists('quantity', $attributes) && ServiceAgreementData::decimal($attributes['quantity'], 'quantity') !== $item->quantity;
            $unitChanged = array_key_exists('unit', $attributes) && $attributes['unit'] !== $item->unit;
            $this->validate([...$item->attributesToArray(), ...$attributes], $quantityChanged || $unitChanged);
            $from = $item->status->value;
            $target = $data['status'] ?? $from;
            if ($target !== $from) {
                if (! in_array($target, $item->status->administrativeTargets($item->approvalRequired()), true)) {
                    throw ValidationException::withMessages(['status' => 'این تغییر وضعیت مجاز نیست؛ تأیید و اصلاح از مسیر بررسی نسخه تحویل انجام می‌شود.']);
                }
                if (in_array($target, ['blocked', 'cancelled'], true) && blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'دلیل مسدودشدن یا لغو را ثبت کنید.']);
                }
                $attributes['status'] = $target;
                if ($target === 'in_progress') {
                    $attributes['started_at'] = $item->started_at ?? now();
                    $attributes['operational_completed_at'] = null;
                }
                if (in_array($target, ['submitted', 'completed'], true)) {
                    $attributes['operational_completed_at'] = $item->operational_completed_at ?? now();
                }
                if ($target === 'completed') {
                    $attributes['completed_at'] = now();
                }
            }
            if (in_array($from, ['completed', 'cancelled', 'accepted', 'submitted'], true) && $target === $from) {
                throw ValidationException::withMessages(['status' => 'کار نهایی یا در انتظار بررسی قابل ویرایش نیست.']);
            }
            if (array_key_exists('quantity', $attributes)) {
                $attributes['quantity'] = ServiceAgreementData::decimal($attributes['quantity'], 'quantity');
            }
            $attributes['lock_version'] = $expectedVersion + 1;
            $attributes['updated_at'] = now();
            $scheduled = false;
            foreach (['scheduled_start_at', 'scheduled_end_at', 'due_at'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $value = $attributes[$field] ? Carbon::parse($attributes[$field])->toDateTimeString() : null;
                    $scheduled = $scheduled || $value !== $item->$field?->toDateTimeString();
                }
            }
            $updated = DB::table('client_project_work_items')->where('id', $item->id)->where('lock_version', $expectedVersion)->update($attributes);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['lock_version' => 'تغییر هم‌زمان ثبت شده است.']);
            }
            $item->refresh();
            $this->links($item, $data);
            $this->event($item, $from, $data['reason'] ?? null, $actor, $scheduled);
            app(ClientProjectDeliveryProgress::class)->refresh($cycle);

            return $item;
        }, 3);
    }

    private function validate(array $data, bool $validateQuantityUnits = true): void
    {
        $quantity = $validateQuantityUnits ? ServiceAgreementData::quantity($data['quantity'] ?? null, $data['unit'] ?? null) : ServiceAgreementData::decimal($data['quantity'] ?? null, 'quantity');
        validator($data, [
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'unit' => [$quantity !== null ? 'required' : 'nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(['internal', 'client'])],
            'scheduled_start_at' => ['nullable', 'date'], 'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'], 'due_at' => ['nullable', 'date'],
        ])->validate();
        if ($quantity !== null && bccomp($quantity, '0', 4) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'مقدار باید مثبت باشد.']);
        }
    }

    private function links(ClientProjectWorkItem $item, array $data): void
    {
        if (array_key_exists('assignees', $data)) {
            $ids = array_unique($data['assignees'] ?? []);
            if (User::query()->whereIn('id', $ids)->where('is_admin', true)->where('status', 'active')->count() !== count($ids)) {
                throw ValidationException::withMessages(['assignees' => 'فقط مدیران فعال قابل انتساب هستند.']);
            }
            $item->assignees()->sync($ids);
        }
        if (array_key_exists('activities', $data)) {
            $ids = array_unique($data['activities'] ?? []);
            if ($item->cycle->delivery_mode === 'tasks' && $ids !== [] || $item->project->activities()->whereIn('id', $ids)->where('client_project_cycle_id', $item->client_project_cycle_id)->count() !== count($ids)) {
                throw ValidationException::withMessages(['activities' => 'فعالیت باید به همین پروژه و دوره ترکیبی تعلق داشته باشد.']);
            }
            $item->activities()->sync($ids);
        }
    }

    private function event(ClientProjectWorkItem $item, ?string $from, ?string $reason, User $actor, bool $scheduled = false): void
    {
        $kind = $from !== $item->status->value ? 'transition' : ($scheduled ? 'scheduled' : 'internal');
        $item->events()->create(['actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $item->status->value, 'reason' => $reason, 'lock_version' => $item->lock_version,
            'kind' => $kind, 'visibility' => $item->visibility === 'client' && $kind !== 'internal' ? 'client' : 'internal', 'created_at' => now()]);
    }
}
