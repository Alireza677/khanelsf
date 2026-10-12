<?php

namespace App\Services;

use App\Models\ClientCycleReport;
use App\Models\ClientDeliveryFile;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\ClientProjectCycle;
use App\Models\ClientProjectCycleCommitment;
use App\Models\ClientProjectPlanVersion;
use App\Models\ClientProjectWorkItem;
use App\Models\ClientProjectWorkItemEvent;
use App\Models\ClientWorkItemDelivery;
use App\Models\ClientWorkItemReview;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use RuntimeException;

/** Explicit administrative exception to immutable-history models; never called by normal deletion. */
final class TestClientProjectCleanup
{
    private const EDGES = [
        'client_project_cycles.client_project_id' => 'client_projects',
        'client_project_plan_versions.client_project_id' => 'client_projects',
        'client_project_activities.client_project_id' => 'client_projects',
        'client_project_work_items.client_project_id' => 'client_projects',
        'client_project_cycles.client_project_plan_version_id' => 'client_project_plan_versions',
        'client_project_cycle_commitments.client_project_plan_version_id' => 'client_project_plan_versions',
        'client_project_cycle_commitments.client_project_cycle_id' => 'client_project_cycles',
        'client_project_activities.client_project_cycle_id' => 'client_project_cycles',
        'client_project_work_items.client_project_cycle_id' => 'client_project_cycles',
        'client_project_work_items.client_project_cycle_commitment_id' => 'client_project_cycle_commitments',
        'client_cycle_reports.client_project_cycle_id' => 'client_project_cycles',
        'client_project_work_item_user.client_project_work_item_id' => 'client_project_work_items',
        'client_project_work_item_activity.client_project_work_item_id' => 'client_project_work_items',
        'client_project_work_item_activity.client_project_activity_id' => 'client_project_activities',
        'client_project_work_item_events.client_project_work_item_id' => 'client_project_work_items',
        'client_work_item_deliveries.client_project_work_item_id' => 'client_project_work_items',
        'client_work_item_reviews.client_work_item_delivery_id' => 'client_work_item_deliveries',
        'client_delivery_files.client_work_item_delivery_id' => 'client_work_item_deliveries',
        'invoices.client_project_cycle_id' => 'client_project_cycles',
        'invoice_items.client_project_id' => 'client_projects',
        'invoice_items.client_project_activity_id' => 'client_project_activities',
        'invoice_activity_claims.client_project_activity_id' => 'client_project_activities',
        'invoice_cycle_claims.client_project_cycle_id' => 'client_project_cycles',
    ];

    private const MODELS = [
        'client_projects' => ClientProject::class,
        'client_project_cycles' => ClientProjectCycle::class,
        'client_project_plan_versions' => ClientProjectPlanVersion::class,
        'client_project_cycle_commitments' => ClientProjectCycleCommitment::class,
        'client_project_work_items' => ClientProjectWorkItem::class,
        'client_project_activities' => ClientProjectActivity::class,
        'client_project_work_item_events' => ClientProjectWorkItemEvent::class,
        'client_work_item_deliveries' => ClientWorkItemDelivery::class,
        'client_work_item_reviews' => ClientWorkItemReview::class,
        'client_delivery_files' => ClientDeliveryFile::class,
        'client_cycle_reports' => ClientCycleReport::class,
    ];

    public function __construct(private readonly Connection $db) {}

    public function inspect(int $projectId, bool $lock = false): ?ClientProjectCleanupPlan
    {
        $project = $this->read('client_projects', ['id' => [$projectId]], $lock)[0] ?? null;
        if (! $project) {
            return null;
        }
        $customer = $this->read('customers', ['id' => [$project['customer_id']]], $lock)[0] ?? [];
        $rows = [];
        $rows['client_project_plan_versions'] = $this->read('client_project_plan_versions', ['client_project_id' => [$projectId]], $lock);
        $plans = array_column($rows['client_project_plan_versions'], 'id');
        $rows['client_project_cycles'] = $this->read('client_project_cycles', ['client_project_id' => [$projectId], 'client_project_plan_version_id' => $plans], $lock);
        $cycles = array_column($rows['client_project_cycles'], 'id');
        $rows['client_project_cycle_commitments'] = $this->read('client_project_cycle_commitments', ['client_project_cycle_id' => $cycles, 'client_project_plan_version_id' => $plans], $lock);
        $commitments = array_column($rows['client_project_cycle_commitments'], 'id');
        $rows['client_project_work_items'] = $this->read('client_project_work_items', ['client_project_id' => [$projectId], 'client_project_cycle_id' => $cycles, 'client_project_cycle_commitment_id' => $commitments], $lock);
        $works = array_column($rows['client_project_work_items'], 'id');
        $rows['client_project_activities'] = $this->read('client_project_activities', ['client_project_id' => [$projectId], 'client_project_cycle_id' => $cycles], $lock);
        $activities = array_column($rows['client_project_activities'], 'id');
        foreach (['client_project_work_item_user', 'client_project_work_item_events', 'client_work_item_deliveries'] as $table) {
            $rows[$table] = $this->read($table, ['client_project_work_item_id' => $works], $lock);
        }
        $rows['client_project_work_item_activity'] = $this->read('client_project_work_item_activity', ['client_project_work_item_id' => $works, 'client_project_activity_id' => $activities], $lock);
        $deliveries = array_column($rows['client_work_item_deliveries'], 'id');
        foreach (['client_work_item_reviews', 'client_delivery_files'] as $table) {
            $rows[$table] = $this->read($table, ['client_work_item_delivery_id' => $deliveries], $lock);
        }
        foreach ($rows['client_delivery_files'] as &$file) {
            // Even a different-disk alias to this exact relative path is conservatively treated as shared.
            $query = $this->db->table('client_delivery_files')->where('path', $file['path'])->orderBy('id');
            $file['path_references'] = ($lock ? $query->lockForUpdate() : $query)->get()->count();
        }
        unset($file);
        $rows['client_cycle_reports'] = $this->read('client_cycle_reports', ['client_project_cycle_id' => $cycles], $lock);
        $financial = [
            'invoice_items' => $this->read('invoice_items', ['client_project_id' => [$projectId], 'client_project_activity_id' => $activities], $lock),
            'invoice_activity_claims' => $this->read('invoice_activity_claims', ['client_project_activity_id' => $activities], $lock),
            'invoice_cycle_claims' => $this->read('invoice_cycle_claims', ['client_project_cycle_id' => $cycles], $lock),
        ];
        $invoiceIds = array_unique(array_merge(...array_map(fn ($records) => array_column($records, 'invoice_id'), $financial)));
        $financial['invoices'] = $this->read('invoices', ['id' => $invoiceIds, 'client_project_cycle_id' => $cycles], $lock);
        $blockers = $this->schemaBlockers($project, $rows, $lock);

        return new ClientProjectCleanupPlan($project, $customer, $rows, $financial, $blockers);
    }

    /** All checks are repeated under locks; no filesystem deletion occurs inside this transaction. */
    public function execute(int $id, string $confirmProject, string $confirmCustomer, string $fingerprint, bool $deleteFiles, ?callable $manifestWritten = null): array
    {
        if (! in_array($this->db->getDriverName(), ['mysql', 'pgsql'], true) || $this->db->getTablePrefix() !== '') {
            throw new RuntimeException('Execution requires MySQL/InnoDB or PostgreSQL with an unprefixed, complete schema.');
        }
        $result = $this->db->transaction(function () use ($id, $confirmProject, $confirmCustomer, $fingerprint, $deleteFiles, $manifestWritten): array {
            $plan = $this->inspect($id, true);
            if (! $plan) {
                throw new RuntimeException('Project is already absent; no destructive action was taken.');
            }
            $plan->confirm($confirmProject, $confirmCustomer, $fingerprint);
            if ($deleteFiles) {
                $this->fileRoot(); // Refuse unsupported storage before changing any database rows.
            }
            $steps = $plan->deletionSteps();
            $manifest = [
                'version' => 1, 'project_id' => $id, 'customer_id' => $plan->project['customer_id'],
                'title' => $plan->project['title'], 'fingerprint' => $fingerprint, 'delete_files' => $deleteFiles,
                'targets' => $steps, 'files' => $plan->rows['client_delivery_files'],
            ];
            $path = $this->writeManifest($manifest);
            if ($manifestWritten) {
                $manifestWritten($path);
            }
            // Deliberately use exact query-builder predicates; immutable model lifecycle rules stay unchanged.
            foreach ($steps as $step) {
                $affected = $this->db->table($step['table'])->where($step['where'])->delete();
                if ($affected !== 1) {
                    throw new RuntimeException("Ownership/count changed in {$step['table']}; transaction rolled back. Manifest: {$path}");
                }
            }

            return ['manifest' => $path, 'data' => $manifest];
        }, 1); // Never automatically retry around manifest/file operations.

        $result['file_results'] = [];
        foreach ($result['data']['files'] as $file) {
            if (! $deleteFiles) {
                $result['file_results'][] = ['path' => $file['path'], 'state' => 'retained'];

                continue;
            }
            try {
                $result['file_results'][] = ['path' => $file['path'], 'state' => $this->removeFile($file)];
            } catch (\Throwable $error) {
                $result['file_results'][] = ['path' => $file['path'], 'state' => 'retained: '.$error->getMessage()];
            }
        }

        return $result;
    }

    /** Read-only verification, including targets captured before deletion and retained/missing physical files. */
    public function verify(int $id, string $manifestPath): array
    {
        $directory = realpath(storage_path('app/private/client-project-cleanup'));
        $path = realpath($manifestPath);
        if (! $directory || ! $path || dirname($path) !== $directory || is_link($manifestPath)
            || ! preg_match('/^project-'.$id.'-[0-9a-f-]{36}\.json$/D', basename($path))) {
            throw new RuntimeException('Use the exact private manifest path printed by this command for this project.');
        }
        $envelope = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $json = json_encode($envelope['data'], JSON_THROW_ON_ERROR);
        if (! hash_equals(hash_hmac('sha256', $json, $this->manifestKey()), (string) ($envelope['signature'] ?? ''))
            || (int) $envelope['data']['project_id'] !== $id) {
            throw new RuntimeException('Manifest signature or project identity is invalid.');
        }
        $remaining = [];
        foreach ($envelope['data']['targets'] as $step) {
            if (! isset(ClientProjectCleanupPlan::KEYS[$step['table']]) || $step['where'] === []) {
                throw new RuntimeException('Unexpected manifest target.');
            }
            // An ID reused or reassigned after cleanup must not be reported as absent.
            $identity = isset($step['where']['id']) ? ['id' => $step['where']['id']] : $step['where'];
            if ($this->db->table($step['table'])->where($identity)->exists()) {
                $remaining[] = $step;
            }
        }
        $files = [];
        foreach ($envelope['data']['files'] as $file) {
            if (! ClientProjectCleanupPlan::validFile($file)) {
                throw new RuntimeException('Unsafe manifest attachment.');
            }
            $physical = $this->fileRoot().DIRECTORY_SEPARATOR.$file['path'];
            $files[] = ['path' => $file['path'], 'state' => file_exists($physical) || is_link($physical) ? 'present' : 'absent'];
        }

        return ['remaining' => $remaining, 'files' => $files,
            'files_pending' => $envelope['data']['delete_files'] && collect($files)->contains(fn ($file) => $file['state'] === 'present'),
            'customer_present' => $this->db->table('customers')->where('id', $envelope['data']['customer_id'])->exists()];
    }

    private function read(string $table, array $matches, bool $lock): array
    {
        $query = $this->db->table($table)->where(function ($query) use ($matches): void {
            $query->whereRaw('1 = 0');
            foreach ($matches as $column => $ids) {
                if ($ids !== []) {
                    $query->orWhereIn($column, $ids);
                }
            }
        });
        $keys = ClientProjectCleanupPlan::KEYS[$table] ?? (in_array($table, ['invoice_items', 'invoices'], true) ? ['id'] : [array_key_first($matches)]);
        foreach (array_filter($keys, fn ($key) => $key === 'id' || str_ends_with($key, '_id')) as $key) {
            $query->orderBy($key);
        }

        return ($lock ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function schemaBlockers(array $project, array $rows, bool $lock): array
    {
        $schema = $this->db->getSchemaBuilder();
        $ids = ['client_projects' => [$project['id']]];
        foreach ($rows as $table => $records) {
            $ids[$table] = array_column($records, 'id');
        }
        $blockers = [];
        $currentSchema = $this->db->getDriverName() === 'pgsql'
            ? $this->db->selectOne('select current_schema() as schema')->schema : $this->db->getDatabaseName();
        if (in_array($this->db->getDriverName(), ['mysql', 'pgsql'], true)) {
            $triggers = $this->db->table('information_schema.triggers')->where('event_object_schema', $currentSchema)
                ->whereIn('event_object_table', array_keys(ClientProjectCleanupPlan::KEYS))->get();
            if ($triggers->isNotEmpty()) {
                $blockers[] = 'Deployed cleanup targets have database triggers; review them separately before cleanup.';
            }
        }
        if ($this->db->getDriverName() === 'mysql') {
            if ((int) $this->db->selectOne('select @@FOREIGN_KEY_CHECKS as fk_checks')->fk_checks !== 1) {
                $blockers[] = 'FOREIGN_KEY_CHECKS is disabled on this connection; cleanup is refused.';
            }
            $external = $this->db->table('information_schema.key_column_usage')->where('referenced_table_schema', $currentSchema)
                ->where('table_schema', '!=', $currentSchema)->whereIn('referenced_table_name', array_keys($ids))->get();
            if ($external->isNotEmpty()) {
                $blockers[] = 'Incoming cross-database foreign keys exist; cleanup is refused.';
            }
        }
        $found = [];
        $scanned = [];
        foreach ($schema->getTables() as $tableInfo) {
            $table = $tableInfo['name'];
            if (isset($tableInfo['schema']) && $tableInfo['schema'] !== $currentSchema) {
                $blockers[] = 'Multiple database schemas require separate review: '.$tableInfo['schema'].'.'.$table;

                continue;
            }
            if ($this->db->getDriverName() === 'mysql' && (isset(ClientProjectCleanupPlan::KEYS[$table])
                || in_array($table, ['invoices', 'invoice_items', 'invoice_cycle_claims', 'invoice_activity_claims'], true))
                && strtolower($tableInfo['engine'] ?? '') !== 'innodb') {
                $blockers[] = "Non-transactional table: {$table}.";
            }
            foreach ($schema->getForeignKeys($table) as $fk) {
                $parent = $fk['foreign_table'];
                if (! array_key_exists($parent, $ids)) {
                    continue;
                }
                if (isset($fk['foreign_schema']) && $fk['foreign_schema'] !== $currentSchema) {
                    $blockers[] = "Cross-schema ownership FK: {$table}.{$fk['name']}.";

                    continue;
                }
                if (count($fk['columns']) !== 1 || $fk['foreign_columns'] !== ['id']) {
                    $blockers[] = "Unsupported incoming FK: {$table}.{$fk['name']}.";

                    continue;
                }
                $column = $fk['columns'][0];
                $edge = $table.'.'.$column;
                $scanned[$edge] = true;
                if ((self::EDGES[$edge] ?? null) === $parent) {
                    $found[$edge] = true;
                } else {
                    $unexpected = $this->read($table, [$column => $ids[$parent]], $lock);
                    if ($unexpected !== []) {
                        $blockers[] = "Unexpected dependency: {$edge} -> {$parent} (".count($unexpected).').';
                    }
                }
            }
            // Detect additional project/financial references without declared FKs, plus polymorphic media/audits.
            $columns = $schema->getColumnListing($table);
            foreach (self::EDGES as $edge => $parent) {
                $column = explode('.', $edge)[1];
                $actual = $table.'.'.$column;
                if (! isset($scanned[$actual]) && ! isset(self::EDGES[$actual]) && in_array($column, $columns, true)) {
                    $unexpected = $this->read($table, [$column => $ids[$parent]], $lock);
                    if ($unexpected !== []) {
                        $blockers[] = "Unexpected untyped dependency: {$actual} (".count($unexpected).').';
                    }
                }
            }
            foreach ($columns as $column) {
                $idColumn = substr($column, 0, -5).'_id';
                if (! str_ends_with($column, '_type') || ! in_array($idColumn, $columns, true)) {
                    continue;
                }
                foreach (self::MODELS as $parent => $class) {
                    $aliases = [$class, ...array_keys(Relation::morphMap(), $class, true)];
                    $query = $this->db->table($table)->whereIn($column, $aliases)->whereIn($idColumn, $ids[$parent]);
                    $unexpected = ($lock ? $query->lockForUpdate() : $query)->get();
                    if ($unexpected->isNotEmpty()) {
                        $blockers[] = "Polymorphic dependency must be reviewed separately: {$table}.{$column} -> {$parent} ({$unexpected->count()}).";
                    }
                }
            }
        }
        foreach (array_diff(array_keys(self::EDGES), array_keys($found)) as $edge) {
            $blockers[] = "Required ownership/financial FK is missing: {$edge}.";
        }

        return $blockers;
    }

    private function manifestKey(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for a signed cleanup manifest.');
        }

        return $key;
    }

    private function writeManifest(array $data): string
    {
        $directory = storage_path('app/private/client-project-cleanup');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create the private cleanup manifest directory.');
        }
        if (is_link($directory)) {
            throw new RuntimeException('Cleanup manifest directory must not be a symlink.');
        }
        $path = $directory.'/project-'.$data['project_id'].'-'.Str::uuid().'.json';
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $envelope = json_encode(['data' => $data, 'signature' => hash_hmac('sha256', $json, $this->manifestKey())], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $handle = fopen($path, 'x');
        if (! $handle) {
            throw new RuntimeException('Cannot create cleanup manifest.');
        }
        try {
            chmod($path, 0600);
            if (fwrite($handle, $envelope) !== strlen($envelope) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException("Cannot persist cleanup manifest: {$path}");
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    private function fileRoot(): string
    {
        $disk = config('filesystems.disks.service_deliveries');
        $root = is_array($disk) ? realpath($disk['root'] ?? '') : false;
        if (($disk['driver'] ?? null) !== 'local' || ($disk['visibility'] ?? null) !== 'private' || ! $root || ! is_dir($root)) {
            throw new RuntimeException('Private delivery files require the existing local private disk/root.');
        }
        foreach (config('filesystems.disks', []) as $name => $other) {
            if ($name === 'service_deliveries' || ($other['driver'] ?? '') !== 'local') {
                continue;
            }
            $otherRoot = realpath($other['root'] ?? '');
            if ($otherRoot && ($root === $otherRoot || str_starts_with($root, $otherRoot.DIRECTORY_SEPARATOR)
                || str_starts_with($otherRoot, $root.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Private delivery root overlaps another local disk; physical deletion/verification refused.');
            }
        }

        return $root;
    }

    private function removeFile(array $file): string
    {
        if (! ClientProjectCleanupPlan::validFile($file)) {
            throw new RuntimeException('Unsafe attachment path.');
        }
        if ($this->db->table('client_delivery_files')->where('path', $file['path'])->exists()) {
            throw new RuntimeException('Path still has a database reference.');
        }
        $root = $this->fileRoot();
        $path = $root;
        foreach (explode('/', $file['path']) as $part) {
            $path .= DIRECTORY_SEPARATOR.$part;
            if (is_link($path)) {
                throw new RuntimeException('Symlink in delivery path; file retained.');
            }
        }
        if (! file_exists($path)) {
            return 'already absent';
        }
        $real = realpath($path);
        if (! $real || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ! is_file($real)
            || filesize($real) !== (int) $file['size'] || ! hash_equals($file['sha256'], hash_file('sha256', $real))) {
            throw new RuntimeException('Path containment or file content changed; file retained.');
        }
        if (! unlink($real)) {
            throw new RuntimeException('Could not unlink this exact attachment.');
        }

        return 'deleted';
    }
}
