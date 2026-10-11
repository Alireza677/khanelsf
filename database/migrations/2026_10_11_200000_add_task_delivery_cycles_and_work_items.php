<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        // SQLite's Blueprint FK alteration rebuilds the table. Dropping the old
        // table can fire legacy ON DELETE SET NULL / CASCADE actions, especially
        // inside a transaction. An inline nullable FK needs no table rebuild.
        if ($sqlite) {
            DB::statement('ALTER TABLE client_project_cycles ADD COLUMN client_project_plan_version_id INTEGER REFERENCES client_project_plan_versions(id) ON DELETE RESTRICT');
        } else {
            Schema::table('client_project_cycles', fn (Blueprint $table) => $table->foreignId('client_project_plan_version_id')->nullable()->constrained()->restrictOnDelete());
        }
        Schema::table('client_project_cycles', function (Blueprint $table): void {
            $table->string('delivery_mode')->default('time');
            $table->string('delivery_status')->nullable()->index();
            $table->json('agreement_snapshot')->nullable();
        });
        Schema::create('client_project_cycle_commitments', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('client_project_cycle_id')->constrained(indexName: 'commitment_cycle_fk')->restrictOnDelete();
            $table->foreignId('client_project_plan_version_id')->constrained(indexName: 'commitment_plan_fk')->restrictOnDelete();
            $table->foreignId('deliverable_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('definition_index');
            $table->string('name');
            $table->text('description')->nullable();
            $sqlite ? $table->string('quantity', 20) : $table->decimal('quantity', 18, 4);
            $table->string('unit');
            $table->boolean('approval_required');
            $table->unsignedInteger('revision_allowance');
            $table->json('scheduling_rules')->nullable();
            $table->json('definition_snapshot');
            $table->timestamps();
            $table->unique(['client_project_cycle_id', 'definition_index'], 'cycle_commitment_definition_unique');
        });
        Schema::create('client_project_work_items', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_project_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_project_cycle_commitment_id')->nullable()->constrained(indexName: 'work_item_commitment_fk')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            ($sqlite ? $table->string('quantity', 20) : $table->decimal('quantity', 18, 4))->nullable();
            $table->string('unit')->nullable();
            $table->string('status')->default('planned')->index();
            $table->string('visibility')->default('internal');
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('operational_completed_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('generation_key')->unique();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->index(['client_project_id', 'client_project_cycle_id'], 'work_item_project_cycle_index');
        });
        Schema::create('client_project_work_item_user', function (Blueprint $table): void {
            $table->foreignId('client_project_work_item_id')->constrained(indexName: 'work_item_assignee_item_fk')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['client_project_work_item_id', 'user_id'], 'work_item_assignee_primary');
        });
        Schema::create('client_project_work_item_activity', function (Blueprint $table): void {
            $table->foreignId('client_project_work_item_id')->constrained(indexName: 'work_item_activity_item_fk')->restrictOnDelete();
            $table->foreignId('client_project_activity_id')->constrained(indexName: 'work_item_activity_activity_fk')->restrictOnDelete();
            $table->primary(['client_project_work_item_id', 'client_project_activity_id'], 'work_item_activity_primary');
        });
        Schema::create('client_project_work_item_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_project_work_item_id')->constrained(indexName: 'work_item_event_item_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->unsignedInteger('lock_version');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_work_item_events');
        Schema::dropIfExists('client_project_work_item_activity');
        Schema::dropIfExists('client_project_work_item_user');
        Schema::dropIfExists('client_project_work_items');
        Schema::dropIfExists('client_project_cycle_commitments');
        Schema::table('client_project_cycles', fn (Blueprint $table) => $table->dropIndex(['delivery_status']));
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        Schema::table('client_project_cycles', function (Blueprint $table) use ($sqlite): void {
            if ($sqlite) {
                $table->dropColumn('client_project_plan_version_id');
            } else {
                $table->dropConstrainedForeignId('client_project_plan_version_id');
            }
            $table->dropColumn(['delivery_mode', 'delivery_status', 'agreement_snapshot']);
        });
    }
};
