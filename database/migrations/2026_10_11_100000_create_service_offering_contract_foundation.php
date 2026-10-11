<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite NUMERIC affinity converts decimal strings to binary floats.
        // Preserve exact decimal strings there; MySQL/PostgreSQL use DECIMAL.
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        $this->createOrResume('service_offerings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        $this->createOrResume('deliverable_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->default('count');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        $this->createOrResume('service_offering_versions', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('service_offering_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft')->index();
            $table->string('delivery_mode')->default('time');
            $table->string('schedule_mode')->default('recurring');
            $table->string('billing_basis')->default('package');
            $table->string('billing_interval')->default('monthly');
            ($sqlite ? $table->string('amount', 20) : $table->decimal('amount', 18, 4))->nullable();
            $table->char('currency_code', 3)->default('IRT');
            $table->text('contract_terms')->nullable();
            $table->json('scheduling_defaults')->nullable();
            $table->json('catalog_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_offering_id', 'version']);
        });
        $this->createOrResume('service_offering_deliverables', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('service_offering_version_id')->constrained(indexName: 'offering_deliverable_version_fk')->restrictOnDelete();
            $table->foreignId('deliverable_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $sqlite ? $table->string('quantity', 20) : $table->decimal('quantity', 18, 4);
            $table->string('unit');
            $table->boolean('approval_required')->default(false);
            $table->unsignedInteger('revision_allowance')->default(0);
            $table->json('scheduling_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        if (! Schema::hasColumn('client_projects', 'delivery_mode')) {
            Schema::table('client_projects', function (Blueprint $table): void {
                $table->string('delivery_mode')->default('time');
            });
        }
        $this->createOrResume('client_project_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_offering_version_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->json('catalog_snapshot');
            $table->json('agreement_snapshot');
            $table->text('override_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['client_project_id', 'version']);
            $table->index(['client_project_id', 'effective_from'], 'project_plan_effective_from_index');
        });
    }

    /** Resume MySQL's partially committed DDL without dropping tables or data. */
    private function createOrResume(string $name, Closure $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);

            return;
        }

        $blueprint = new Blueprint($name);
        $blueprint->create();
        $definition($blueprint);
        $connection = Schema::getConnection();
        // Expand fluent indexes exactly as the framework does during creation.
        $blueprint->toSql($connection, $connection->getSchemaGrammar());
        $columns = Schema::getColumnListing($name);
        foreach ($blueprint->getColumns() as $column) {
            if (! in_array($column->name, $columns, true)) {
                throw new RuntimeException("Cannot resume {$name}: expected column {$column->name} is missing.");
            }
        }

        $foreignKeys = Schema::getForeignKeys($name);
        $indexes = Schema::getIndexes($name);
        $missing = [];
        foreach ($blueprint->getCommands() as $command) {
            if ($command->name === 'foreign') {
                $existing = collect($foreignKeys)->first(fn ($key) => $key['columns'] === $command->columns);
                if (! $existing) {
                    $missing[] = $command;
                } elseif ($existing['foreign_table'] !== $connection->getTablePrefix().$command->on
                    || $existing['foreign_columns'] !== (array) $command->references
                    || strtolower($existing['on_delete']) !== strtolower($command->onDelete ?? 'no action')) {
                    throw new RuntimeException("Cannot resume {$name}: conflicting foreign key on ".implode(', ', $command->columns));
                }
            } elseif (in_array($command->name, ['index', 'unique', 'primary'], true)) {
                $exists = collect($indexes)->contains(fn ($index) => $index['columns'] === $command->columns
                    && ($command->name !== 'unique' || $index['unique'])
                    && ($command->name !== 'primary' || $index['primary']));
                if (! $exists) {
                    $missing[] = $command;
                }
            }
        }

        if ($missing === []) {
            return;
        }
        if ($connection->getDriverName() === 'sqlite' && collect($missing)->contains(fn ($command) => $command->name === 'foreign')) {
            // SQLite rebuilds a table to add FKs. Only repair a leaf table:
            // rebuilding a referenced table could fire legacy delete actions.
            foreach (Schema::getTables() as $table) {
                foreach (Schema::getForeignKeys($table['name']) as $key) {
                    if ($key['foreign_table'] === $connection->getTablePrefix().$name) {
                        throw new RuntimeException("Cannot safely resume referenced SQLite table {$name} with missing foreign keys.");
                    }
                }
            }
        }
        Schema::table($name, function (Blueprint $table) use ($missing): void {
            foreach ($missing as $command) {
                $definition = $table->{$command->name}($command->columns, $command->index);
                foreach ($command->getAttributes() as $attribute => $value) {
                    $definition->$attribute = $value;
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_plan_versions');
        Schema::table('client_projects', fn (Blueprint $table) => $table->dropColumn('delivery_mode'));
        Schema::dropIfExists('service_offering_deliverables');
        Schema::dropIfExists('service_offering_versions');
        Schema::dropIfExists('deliverable_types');
        Schema::dropIfExists('service_offerings');
    }
};
