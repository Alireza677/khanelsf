<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\DeliverableType;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ServiceDeliveryMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_ddl_for_all_delivery_phases_has_no_identifiers_over_64_characters(): void
    {
        $connection = new MySqlConnection(fn () => throw new \LogicException('SQL compilation must not connect to MySQL.'), 'service_delivery_testing', '', ['driver' => 'mysql']);
        $connection->useDefaultSchemaGrammar();
        $builder = Mockery::mock(MySqlBuilder::class, [$connection])->makePartial();
        $builder->shouldReceive('hasTable')->andReturn(false);
        $builder->shouldReceive('hasColumn')->andReturn(false);
        $original = Schema::getFacadeRoot();
        try {
            Schema::swap($builder);
            $queries = $connection->pretend(function (): void {
                (require database_path('migrations/2026_10_11_100000_create_service_offering_contract_foundation.php'))->up();
                (require database_path('migrations/2026_10_11_200000_add_task_delivery_cycles_and_work_items.php'))->up();
                (require database_path('migrations/2026_10_11_300000_add_private_deliveries_reviews_and_cycle_reports.php'))->up();
            });
        } finally {
            Schema::swap($original);
        }
        $this->assertNotEmpty($queries);
        $names = [];
        foreach ($queries as $query) {
            preg_match_all('/`([^`]+)`/', $query['query'], $matches);
            foreach ($matches[1] as $name) {
                $names[] = $name;
                $this->assertLessThanOrEqual(64, strlen($name), $name);
            }
        }
        $this->assertContains('offering_deliverable_version_fk', $names);
        $this->assertContains('project_plan_effective_from_index', $names);
    }

    public function test_retry_completes_missing_deliverable_foreign_keys_without_removing_rows(): void
    {
        // Reproduce the failed MySQL create: the full leaf table exists, but
        // both ALTER TABLE foreign key commands never completed.
        $migration = require database_path('migrations/2026_10_11_100000_create_service_offering_contract_foundation.php');
        $migration->up(); // Complete existing tables must also be retry-safe.
        Schema::drop('service_offering_deliverables');
        Schema::create('service_offering_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_offering_version_id');
            $table->unsignedBigInteger('deliverable_type_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('quantity', 20);
            $table->string('unit');
            $table->boolean('approval_required')->default(false);
            $table->unsignedInteger('revision_allowance')->default(0);
            $table->json('scheduling_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        $service = Service::create(['name' => 'خدمت', 'slug' => 'migration-retry', 'status' => 'draft']);
        $offering = ServiceOffering::create(['service_id' => $service->id, 'name' => 'بسته', 'code' => 'RETRY']);
        $type = DeliverableType::create(['name' => 'تحویل', 'code' => 'RETRY']);
        $version = ServiceOfferingVersion::create(['service_offering_id' => $offering->id, 'version' => 1]);
        DB::table('service_offering_deliverables')->insert(['service_offering_version_id' => $version->id, 'deliverable_type_id' => $type->id, 'name' => 'محفوظ', 'quantity' => '12345678901234.1234', 'unit' => 'count']);
        $before = DB::table('service_offering_deliverables')->first();
        $this->assertCount(0, Schema::getForeignKeys('service_offering_deliverables'));
        $migration->up();
        $this->assertCount(2, Schema::getForeignKeys('service_offering_deliverables'));
        $this->assertEquals($before, DB::table('service_offering_deliverables')->first());
        $migration->up();
        $this->assertEquals($before, DB::table('service_offering_deliverables')->first());
        $this->assertDatabaseCount('service_offerings', 1);
        $this->assertDatabaseCount('service_offering_versions', 1);
    }

    public function test_retry_restores_a_missing_plan_index_and_preserves_project_data(): void
    {
        $project = ClientProject::factory()->create();
        $before = $project->fresh()->getAttributes();
        Schema::table('client_project_plan_versions', fn (Blueprint $table) => $table->dropIndex('project_plan_effective_from_index'));
        (require database_path('migrations/2026_10_11_100000_create_service_offering_contract_foundation.php'))->up();
        $this->assertTrue(Schema::hasIndex('client_project_plan_versions', ['client_project_id', 'effective_from']));
        $this->assertSame($before, $project->fresh()->getAttributes());
    }
}
