<?php

namespace App\Services\Revisions;

use App\Models\Page;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Collection;

final class RevisionService
{
    public function __construct(private readonly PageRevisionSnapshot $snapshots) {}

    public function recordPageSave(
        Page $page,
        array $before,
        array $after,
        ?int $actorId,
        ?int $restoredFromRevisionId = null,
    ): ?Revision {
        $before = $this->snapshots->fromEditorData($before);
        $after = $this->snapshots->fromEditorData($after);

        $latest = $page->revisions()->latest('revision_number')->first();

        if (! $latest) {
            $latest = $this->create(
                $page,
                $before,
                $actorId,
                'baseline',
            );
        }

        $afterChecksum = $this->snapshots->checksum($after);

        if (hash_equals($latest->checksum, $afterChecksum)) {
            return null;
        }

        return $this->create(
            $page,
            $after,
            $actorId,
            $restoredFromRevisionId ? 'restore' : 'save',
            $restoredFromRevisionId,
        );
    }

    public function latestForPage(Page $page, int $limit = 30): Collection
    {
        return $page->revisions()
            ->with(['creator:id,name', 'restoredFrom:id,revision_number'])
            ->select([
                'id', 'revisionable_type', 'revisionable_id', 'revision_number',
                'created_by', 'checksum', 'event', 'restored_from_revision_id', 'created_at',
            ])
            ->latest('revision_number')
            ->limit($limit)
            ->get();
    }

    private function create(
        Page $page,
        array $snapshot,
        ?int $actorId,
        string $event,
        ?int $restoredFromRevisionId = null,
    ): Revision {
        $number = ((int) $page->revisions()->max('revision_number')) + 1;

        return $page->revisions()->create([
            'revision_number' => $number,
            'created_by' => $actorId,
            'snapshot' => $snapshot,
            'checksum' => $this->snapshots->checksum($snapshot),
            'event' => $event,
            'restored_from_revision_id' => $restoredFromRevisionId,
        ]);
    }
}
