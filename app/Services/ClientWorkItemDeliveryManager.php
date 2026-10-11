<?php

namespace App\Services;

use App\Models\ClientProjectCycle;
use App\Models\ClientProjectWorkItem;
use App\Models\ClientWorkItemDelivery;
use App\Models\ClientWorkItemReview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ClientWorkItemDeliveryManager
{
    public const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain', 'text/csv', 'video/mp4', 'video/quicktime', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'application/zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    /** Files are uploaded objects grouped by client/internal; paths are never accepted from callers. */
    public function submit(ClientProjectWorkItem $item, User $actor, int $expectedVersion, ?string $message, array $files = []): ClientWorkItemDelivery
    {
        Gate::forUser($actor)->authorize('update', $item->project);
        validator(['message' => $message, 'files' => $files], [
            'message' => ['nullable', 'string', 'max:10000'], 'files' => ['array'],
            'files.*' => ['array', 'max:10'], 'files.*.*' => ['required', 'file', 'max:102400', 'mimetypes:'.implode(',', self::MIME_TYPES)],
        ])->validate();
        if (array_diff(array_keys($files), ['client', 'internal'])) {
            $this->conflict('files', 'نوع دسترسی فایل نامعتبر است.');
        }
        $stored = [];
        try {
            return DB::transaction(function () use ($item, $actor, $expectedVersion, $message, $files, &$stored) {
                $cycle = ClientProjectCycle::whereKey($item->client_project_cycle_id)->lockForUpdate()->firstOrFail();
                $item = ClientProjectWorkItem::whereKey($item)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $item->project);
                $this->assertVersion($item, $expectedVersion);
                // Legacy phase-2 submissions without delivery records can receive their first version.
                if ($item->status->value !== 'in_progress' && ! ($item->status->value === 'submitted' && ! $item->deliveries()->exists())) {
                    $this->conflict('status', 'برای ارسال، کار باید در حال اجرا باشد.');
                }
                if ($item->approvalRequired() && $item->visibility !== 'client') {
                    $this->conflict('visibility', 'کار نیازمند تأیید باید پیش از ارسال برای مشتری قابل مشاهده باشد.');
                }
                if ($item->visibility !== 'client' && ! empty($files['client'])) {
                    $this->conflict('files', 'فایل مشتری به کار داخلی تعلق نمی‌گیرد.');
                }
                $delivery = $item->deliveries()->create([
                    'version' => ($item->deliveries()->max('version') ?? 0) + 1, 'submitted_by' => $actor->id, 'submitted_at' => now(),
                    'message' => $message, 'visibility' => $item->visibility, 'approval_required' => $item->approvalRequired(),
                    'revision_allowance' => $item->commitment?->revision_allowance ?? 0,
                ]);
                foreach ($files as $visibility => $uploads) {
                    foreach ($uploads as $upload) {
                        /** @var UploadedFile $upload */
                        $path = 'deliveries/'.$delivery->id.'/'.Str::uuid();
                        $stored[] = $path;
                        if (Storage::disk('service_deliveries')->putFileAs('deliveries/'.$delivery->id, $upload, basename($path)) === false) {
                            throw new \RuntimeException('Private delivery file could not be stored.');
                        }
                        $delivery->files()->create([
                            'uploaded_by' => $actor->id, 'disk' => 'service_deliveries', 'path' => $path,
                            'original_name' => mb_substr(str_replace(["\r", "\n"], '', basename(str_replace('\\', '/', $upload->getClientOriginalName()))), 0, 255),
                            'mime_type' => $upload->getMimeType(), 'size' => $upload->getSize(),
                            'sha256' => hash_file('sha256', $upload->getRealPath()), 'visibility' => $visibility, 'uploaded_at' => now(),
                        ]);
                    }
                }
                $from = $item->status->value;
                $this->writeState($item, $expectedVersion, ['status' => 'submitted', 'operational_completed_at' => now()]);
                $this->event($item, $actor, $from, 'submitted');
                app(ClientProjectDeliveryProgress::class)->refresh($cycle);

                return $delivery;
            }); // No automatic retry around external file writes.
        } catch (\Throwable $error) {
            foreach ($stored as $path) {
                Storage::disk('service_deliveries')->delete($path);
            }
            throw $error;
        }
    }

    public function review(ClientWorkItemDelivery $delivery, User $actor, int $expectedVersion, string $decision, ?string $reason): ClientWorkItemReview
    {
        validator(['decision' => $decision, 'reason' => $reason], [
            'decision' => ['required', Rule::in(['accepted', 'revision_requested'])],
            'reason' => [Rule::requiredIf($decision === 'revision_requested'), 'nullable', 'string', 'max:10000'],
        ])->validate();

        return DB::transaction(function () use ($delivery, $actor, $expectedVersion, $decision, $reason) {
            $cycle = ClientProjectCycle::whereKey($delivery->workItem->client_project_cycle_id)->lockForUpdate()->firstOrFail();
            $item = ClientProjectWorkItem::whereKey($delivery->client_project_work_item_id)->lockForUpdate()->firstOrFail();
            $delivery = $item->deliveries()->whereKey($delivery)->lockForUpdate()->firstOrFail();
            app(ClientDeliveryAccess::class)->authorize($actor, $item, true, true);
            $this->assertVersion($item, $expectedVersion);
            $latest = $item->deliveries()->reorder()->orderByDesc('version')->lockForUpdate()->first();
            if ($delivery->visibility !== 'client' || ! $delivery->approval_required || $item->status->value !== 'submitted'
                || $latest?->id !== $delivery->id || $delivery->review()->lockForUpdate()->first()) {
                $this->conflict('delivery', 'این نسخه دیگر قابل بررسی نیست؛ صفحه را تازه‌سازی کنید.');
            }
            $revisions = $decision === 'revision_requested' ? DB::table('client_work_item_reviews as reviews')
                ->join('client_work_item_deliveries as deliveries', 'deliveries.id', '=', 'reviews.client_work_item_delivery_id')
                ->where('deliveries.client_project_work_item_id', $item->id)->where('reviews.decision', 'revision_requested')
                ->select('reviews.id')->lockForUpdate()->get()->count() : 0;
            if ($decision === 'revision_requested' && $revisions >= $delivery->revision_allowance) {
                $this->conflict('reason', 'تعداد اصلاحات قراردادی به پایان رسیده است؛ با مدیر تماس بگیرید.');
            }
            $review = $delivery->review()->create(['reviewed_by' => $actor->id, 'decision' => $decision, 'reason' => $reason, 'reviewed_at' => now()]);
            $this->writeState($item, $expectedVersion, ['status' => $decision, 'accepted_at' => $decision === 'accepted' ? now() : null]);
            $this->event($item, $actor, 'submitted', $decision);
            app(ClientProjectDeliveryProgress::class)->refresh($cycle);

            return $review;
        }, 3);
    }

    private function assertVersion(ClientProjectWorkItem $item, int $version): void
    {
        if ($item->lock_version !== $version) {
            $this->conflict('lock_version', 'کار تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
        }
    }

    private function writeState(ClientProjectWorkItem $item, int $version, array $attributes): void
    {
        if (DB::table('client_project_work_items')->where('id', $item->id)->where('lock_version', $version)->update([...$attributes, 'lock_version' => $version + 1, 'updated_at' => now()]) !== 1) {
            $this->conflict('lock_version', 'تغییر هم‌زمان ثبت شده است.');
        }
        $item->refresh();
    }

    private function event(ClientProjectWorkItem $item, User $actor, string $from, string $to): void
    {
        $item->events()->create(['actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $to, 'reason' => null,
            'lock_version' => $item->lock_version, 'kind' => 'transition', 'visibility' => $item->visibility, 'created_at' => now()]);
    }

    private function conflict(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
