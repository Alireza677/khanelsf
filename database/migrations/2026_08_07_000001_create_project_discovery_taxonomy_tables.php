<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_discovery_vocabularies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('project_discovery_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_discovery_vocabulary_id')
                ->constrained('project_discovery_vocabularies')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_discovery_vocabulary_id', 'slug'], 'project_discovery_terms_vocabulary_slug_unique');
            $table->index(['project_discovery_vocabulary_id', 'is_active', 'sort_order'], 'project_discovery_terms_browse_index');
        });

        Schema::create('project_discovery_term_project', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_discovery_term_id')
                ->constrained('project_discovery_terms')
                ->cascadeOnDelete();

            $table->primary(['project_id', 'project_discovery_term_id'], 'project_discovery_term_project_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_discovery_term_project');
        Schema::dropIfExists('project_discovery_terms');
        Schema::dropIfExists('project_discovery_vocabularies');
    }
};
