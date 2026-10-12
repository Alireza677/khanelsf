<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/** A source-specific maintenance plan, not a project lifecycle operation. No database or file writes. */
final class ClientProjectCleanupPlan
{
    public const KEYS = [
        'client_delivery_files' => ['id', 'client_work_item_delivery_id', 'disk', 'path'],
        'client_work_item_reviews' => ['id', 'client_work_item_delivery_id'],
        'client_work_item_deliveries' => ['id', 'client_project_work_item_id'],
        'client_project_work_item_events' => ['id', 'client_project_work_item_id'],
        'client_project_work_item_user' => ['client_project_work_item_id', 'user_id'],
        'client_project_work_item_activity' => ['client_project_work_item_id', 'client_project_activity_id'],
        'client_project_work_items' => ['id', 'client_project_id', 'client_project_cycle_id', 'client_project_cycle_commitment_id'],
        'client_project_cycle_commitments' => ['id', 'client_project_cycle_id', 'client_project_plan_version_id'],
        'client_cycle_reports' => ['id', 'client_project_cycle_id'],
        'client_project_activities' => ['id', 'client_project_id', 'client_project_cycle_id'],
        'client_project_cycles' => ['id', 'client_project_id', 'client_project_plan_version_id'],
        'client_project_plan_versions' => ['id', 'client_project_id'],
        'client_projects' => ['id', 'customer_id'],
    ];

    public array $blockers;

    public function __construct(
        public readonly array $project,
        public readonly array $customer,
        public readonly array $rows,
        public readonly array $financial,
        array $blockers = [],
    ) {
        $this->blockers = $blockers;
        $this->validateOwnership();
        foreach ($financial as $table => $records) {
            if ($records !== []) {
                $this->blockers[] = "Protected financial records: {$table} (".count($records).')';
            }
        }
        foreach ($rows['client_project_cycles'] ?? [] as $cycle) {
            if (($cycle['invoiced_at'] ?? null) !== null || ($cycle['status'] ?? '') === 'invoiced') {
                $this->blockers[] = "Cycle {$cycle['id']} has invoiced state/history.";
            }
        }
        foreach ($rows['client_delivery_files'] ?? [] as $file) {
            if (! self::validFile($file)) {
                $this->blockers[] = "Attachment {$file['id']} has an unsafe disk/path or invalid checksum.";
            }
            if (($file['path_references'] ?? 0) !== 1) {
                $this->blockers[] = "Attachment {$file['id']} has shared or unverified path references.";
            }
        }
        $this->blockers = array_values(array_unique($this->blockers));
    }

    public static function projectId(mixed $value): int
    {
        if (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/D', $value) || strlen($value) > strlen((string) PHP_INT_MAX)
            || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('Project ID must be a single canonical positive integer.');
        }

        return (int) $value;
    }

    public static function validFile(array $file): bool
    {
        $delivery = $file['client_work_item_delivery_id'] ?? null;

        return ($file['disk'] ?? null) === 'service_deliveries'
            && preg_match('/^deliveries\/[1-9][0-9]*\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $file['path'] ?? '') === 1
            && explode('/', $file['path'])[1] === (string) $delivery
            && preg_match('/^[0-9a-f]{64}$/D', $file['sha256'] ?? '') === 1
            && isset($file['size']) && is_numeric($file['size']) && $file['size'] >= 0;
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->project, $this->customer, $this->rows, $this->financial, $this->blockers], JSON_THROW_ON_ERROR));
    }

    public function confirm(mixed $project, mixed $customer, mixed $fingerprint): void
    {
        if ($this->blockers !== []) {
            throw new RuntimeException('Deletion refused: resolve all blockers first.');
        }
        if ((string) $project !== (string) $this->project['id'] || (string) $customer !== (string) $this->project['customer_id']
            || ! is_string($fingerprint) || ! hash_equals($this->fingerprint(), $fingerprint)) {
            throw new RuntimeException('Confirmation does not match the reviewed project, customer and current fingerprint. Run dry-run again.');
        }
    }

    /** Exact row predicates in child-first order. Never include shared or financial tables. */
    public function deletionSteps(): array
    {
        if ($this->blockers !== []) {
            throw new RuntimeException('A blocked plan cannot produce deletion targets.');
        }
        $steps = [];
        foreach (self::KEYS as $table => $keys) {
            $records = $table === 'client_projects' ? [$this->project] : ($this->rows[$table] ?? []);
            foreach ($records as $record) {
                if (array_diff($keys, array_keys($record)) !== []) {
                    throw new RuntimeException("Missing ownership keys for {$table}.");
                }
                $steps[] = ['table' => $table, 'where' => array_intersect_key($record, array_flip($keys))];
            }
        }

        return $steps;
    }

    private function validateOwnership(): void
    {
        $id = (string) $this->project['id'];
        if ((string) ($this->customer['id'] ?? '') !== (string) $this->project['customer_id']) {
            $this->blockers[] = 'Project/customer ownership is invalid.';
        }
        $sets = [];
        foreach ($this->rows as $table => $records) {
            $sets[$table] = array_column($records, null, 'id');
        }
        $belongs = static fn (string $table, mixed $key): bool => $key !== null && isset($sets[$table][$key]);
        foreach (['client_project_plan_versions', 'client_project_cycles', 'client_project_work_items', 'client_project_activities'] as $table) {
            foreach ($this->rows[$table] ?? [] as $row) {
                if ((string) $row['client_project_id'] !== $id) {
                    $this->blockers[] = "Cross-project ownership in {$table}: {$row['id']}.";
                }
            }
        }
        foreach ($this->rows['client_project_cycles'] ?? [] as $row) {
            if ($row['client_project_plan_version_id'] !== null && ! $belongs('client_project_plan_versions', $row['client_project_plan_version_id'])) {
                $this->blockers[] = "Cycle {$row['id']} references another project's plan.";
            }
        }
        foreach ($this->rows['client_project_cycle_commitments'] ?? [] as $row) {
            $cycle = $sets['client_project_cycles'][$row['client_project_cycle_id']] ?? null;
            if (! $cycle || ! $belongs('client_project_plan_versions', $row['client_project_plan_version_id'])
                || (string) $cycle['client_project_plan_version_id'] !== (string) $row['client_project_plan_version_id']) {
                $this->blockers[] = "Commitment {$row['id']} has inconsistent cycle/plan ownership.";
            }
        }
        foreach ($this->rows['client_project_work_items'] ?? [] as $row) {
            $commitment = $sets['client_project_cycle_commitments'][$row['client_project_cycle_commitment_id']] ?? null;
            if (! $belongs('client_project_cycles', $row['client_project_cycle_id']) || ($row['client_project_cycle_commitment_id'] !== null
                && (! $commitment || (string) $commitment['client_project_cycle_id'] !== (string) $row['client_project_cycle_id']))) {
                $this->blockers[] = "WorkItem {$row['id']} has inconsistent cycle/commitment ownership.";
            }
        }
        foreach ($this->rows['client_project_activities'] ?? [] as $row) {
            if ($row['client_project_cycle_id'] !== null && ! $belongs('client_project_cycles', $row['client_project_cycle_id'])) {
                $this->blockers[] = "Activity {$row['id']} references another project's cycle.";
            }
        }
        foreach ([
            'client_cycle_reports' => ['client_project_cycle_id', 'client_project_cycles'],
            'client_work_item_deliveries' => ['client_project_work_item_id', 'client_project_work_items'],
            'client_project_work_item_events' => ['client_project_work_item_id', 'client_project_work_items'],
            'client_project_work_item_user' => ['client_project_work_item_id', 'client_project_work_items'],
            'client_delivery_files' => ['client_work_item_delivery_id', 'client_work_item_deliveries'],
            'client_work_item_reviews' => ['client_work_item_delivery_id', 'client_work_item_deliveries'],
        ] as $table => [$foreign, $parent]) {
            foreach ($this->rows[$table] ?? [] as $row) {
                if (! $belongs($parent, $row[$foreign])) {
                    $this->blockers[] = "Invalid nested ownership in {$table}.";
                }
            }
        }
        foreach ($this->rows['client_project_work_item_activity'] ?? [] as $row) {
            $work = $sets['client_project_work_items'][$row['client_project_work_item_id']] ?? null;
            $activity = $sets['client_project_activities'][$row['client_project_activity_id']] ?? null;
            if (! $work || ! $activity || (string) $work['client_project_cycle_id'] !== (string) $activity['client_project_cycle_id']) {
                $this->blockers[] = 'WorkItem/activity pivot crosses the selected project or cycle.';
            }
        }
    }
}
