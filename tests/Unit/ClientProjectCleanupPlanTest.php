<?php

namespace Tests\Unit;

use App\Console\Commands\CleanupTestClientProject;
use App\Services\ClientProjectCleanupPlan;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Pure arrays and command definitions only: no application boot, PDO, migrations or database deletion. */
class ClientProjectCleanupPlanTest extends TestCase
{
    private function fixture(): array
    {
        return [
            'client_project_plan_versions' => [['id' => 10, 'client_project_id' => 7]],
            'client_project_cycles' => [['id' => 20, 'client_project_id' => 7, 'client_project_plan_version_id' => 10, 'status' => 'active', 'invoiced_at' => null]],
            'client_project_cycle_commitments' => [['id' => 30, 'client_project_cycle_id' => 20, 'client_project_plan_version_id' => 10]],
            'client_project_work_items' => [['id' => 40, 'client_project_id' => 7, 'client_project_cycle_id' => 20, 'client_project_cycle_commitment_id' => 30]],
            'client_project_activities' => [['id' => 50, 'client_project_id' => 7, 'client_project_cycle_id' => 20]],
            'client_project_work_item_user' => [['client_project_work_item_id' => 40, 'user_id' => 999]],
            'client_project_work_item_activity' => [['client_project_work_item_id' => 40, 'client_project_activity_id' => 50]],
            'client_project_work_item_events' => [['id' => 60, 'client_project_work_item_id' => 40]],
            'client_work_item_deliveries' => [['id' => 70, 'client_project_work_item_id' => 40]],
            'client_work_item_reviews' => [['id' => 80, 'client_work_item_delivery_id' => 70]],
            'client_delivery_files' => [['id' => 90, 'client_work_item_delivery_id' => 70, 'disk' => 'service_deliveries',
                'path' => 'deliveries/70/12345678-1234-1234-1234-123456789abc', 'sha256' => str_repeat('a', 64), 'size' => 100, 'path_references' => 1]],
            'client_cycle_reports' => [['id' => 100, 'client_project_cycle_id' => 20]],
        ];
    }

    private function plan(?array $rows = null, array $financial = []): ClientProjectCleanupPlan
    {
        return new ClientProjectCleanupPlan(['id' => 7, 'customer_id' => 5, 'title' => 'Explicit test project', 'delivery_mode' => 'hybrid'],
            ['id' => 5, 'display_name' => 'Shared customer'], $rows ?? $this->fixture(), $financial);
    }

    public function test_exact_command_signature_has_dry_run_default_and_independent_strong_confirmation(): void
    {
        $command = new CleanupTestClientProject;
        $this->assertSame('client-project:cleanup-test', $command->getName());
        $definition = $command->getDefinition();
        $this->assertTrue($definition->getArgument('project_id')->isRequired());
        $this->assertFalse($definition->getOption('execute')->getDefault());
        $this->assertFalse($definition->getOption('delete-files')->getDefault());
        foreach (['confirm-project', 'confirm-customer', 'fingerprint', 'verify-manifest'] as $option) {
            $this->assertTrue($definition->getOption($option)->acceptValue());
        }
        $this->assertTrue($definition->hasOption('dry-run'));
    }

    public function test_invalid_ids_are_never_coerced_into_another_project(): void
    {
        foreach (['0', '-1', '01', '7junk', '7,8', '7.0', ' 7', '7 ', '7e1', '', str_repeat('9', 30), ['7']] as $id) {
            try {
                ClientProjectCleanupPlan::projectId($id);
                $this->fail('Invalid project ID accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(7, ClientProjectCleanupPlan::projectId('7'));
    }

    public function test_confirmation_requires_project_customer_and_fresh_fingerprint(): void
    {
        $plan = $this->plan();
        $plan->confirm('7', '5', $plan->fingerprint());
        $this->addToAssertionCount(1);
        foreach ([['8', '5', $plan->fingerprint()], ['7', '6', $plan->fingerprint()], ['7', '5', str_repeat('0', 64)]] as $confirmation) {
            try {
                $plan->confirm(...$confirmation);
                $this->fail('Mismatched confirmation accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $changed = $this->fixture();
        $changed['client_project_work_items'][0]['lock_version'] = 1;
        $this->assertNotSame($plan->fingerprint(), $this->plan($changed)->fingerprint());
    }

    public function test_all_financial_records_block_even_cancelled_or_draft_invoices_and_orphan_claims(): void
    {
        foreach (['invoices', 'invoice_items', 'invoice_activity_claims', 'invoice_cycle_claims', 'payments'] as $table) {
            $plan = $this->plan(financial: [$table => [['id' => 888, 'status' => 'cancelled']]]);
            $this->assertNotEmpty($plan->blockers);
            try {
                $plan->deletionSteps();
                $this->fail('Protected financial plan returned targets.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $rows = $this->fixture();
        $rows['client_project_cycles'][0]['invoiced_at'] = '2026-10-12 00:00:00';
        $this->assertNotEmpty($this->plan($rows)->blockers);
    }

    public function test_foreign_project_and_inconsistent_nested_ownership_are_blocked(): void
    {
        foreach ([
            ['client_project_work_items', 'client_project_id'],
            ['client_project_cycles', 'client_project_plan_version_id'],
            ['client_project_cycle_commitments', 'client_project_cycle_id'],
            ['client_project_activities', 'client_project_cycle_id'],
            ['client_work_item_deliveries', 'client_project_work_item_id'],
            ['client_work_item_reviews', 'client_work_item_delivery_id'],
            ['client_delivery_files', 'client_work_item_delivery_id'],
            ['client_cycle_reports', 'client_project_cycle_id'],
            ['client_project_work_item_events', 'client_project_work_item_id'],
            ['client_project_work_item_user', 'client_project_work_item_id'],
            ['client_project_work_item_activity', 'client_project_activity_id'],
        ] as [$table, $field]) {
            $rows = $this->fixture();
            $rows[$table][0][$field] = 999;
            $this->assertNotEmpty($this->plan($rows)->blockers, $table);
        }
        $rows = $this->fixture();
        $rows['client_project_activities'][0]['client_project_cycle_id'] = null;
        $this->assertNotEmpty($this->plan($rows)->blockers);
    }

    public function test_private_paths_reject_traversal_other_deliveries_shared_paths_and_disks(): void
    {
        $file = $this->fixture()['client_delivery_files'][0];
        $this->assertTrue(ClientProjectCleanupPlan::validFile($file));
        foreach (['../secret', 'deliveries/71/12345678-1234-1234-1234-123456789abc', '/deliveries/70/file', 'deliveries/70/../file', 'deliveries\\70\\file', 'deliveries/70/', 'deliveries/70/12345678-1234-1234-1234-123456789abc/extra'] as $path) {
            $this->assertFalse(ClientProjectCleanupPlan::validFile([...$file, 'path' => $path]));
        }
        $this->assertFalse(ClientProjectCleanupPlan::validFile([...$file, 'disk' => 'public']));
        $rows = $this->fixture();
        $rows['client_delivery_files'][0]['path_references'] = 2;
        $this->assertNotEmpty($this->plan($rows)->blockers);
    }

    public function test_child_first_plan_targets_only_exact_owned_rows_and_keeps_shared_financial_entities(): void
    {
        $plan = $this->plan();
        $this->assertSame([], $plan->blockers);
        $steps = $plan->deletionSteps();
        $tables = array_column($steps, 'table');
        $this->assertSame('client_projects', $tables[array_key_last($tables)]);
        foreach ([['client_delivery_files', 'client_work_item_deliveries'], ['client_work_item_reviews', 'client_work_item_deliveries'],
            ['client_work_item_deliveries', 'client_project_work_items'], ['client_project_work_item_activity', 'client_project_activities'],
            ['client_project_work_items', 'client_project_cycle_commitments'], ['client_cycle_reports', 'client_project_cycles'],
            ['client_project_activities', 'client_project_cycles'], ['client_project_cycles', 'client_project_plan_versions']] as [$child, $parent]) {
            $this->assertLessThan(array_search($parent, $tables), array_search($child, $tables));
        }
        foreach (['customers', 'users', 'services', 'service_offerings', 'service_offering_versions', 'deliverable_types', 'invoices', 'invoice_items', 'invoice_activity_claims', 'invoice_cycle_claims', 'orders', 'media'] as $shared) {
            $this->assertNotContains($shared, $tables);
        }
        foreach ($steps as $step) {
            $this->assertSame(ClientProjectCleanupPlan::KEYS[$step['table']], array_keys($step['where']));
        }
    }

    public function test_partially_missing_dependents_and_empty_time_project_are_safe(): void
    {
        $rows = $this->fixture();
        $rows['client_work_item_reviews'] = [];
        $rows['client_delivery_files'] = [];
        $rows['client_cycle_reports'] = [];
        $this->assertSame([], $this->plan($rows)->blockers);
        $this->assertNotEmpty($this->plan($rows)->deletionSteps());
        $empty = $this->plan([]);
        $this->assertSame([], $empty->blockers);
        $this->assertSame([['table' => 'client_projects', 'where' => ['id' => 7, 'customer_id' => 5]]], $empty->deletionSteps());
    }
}
