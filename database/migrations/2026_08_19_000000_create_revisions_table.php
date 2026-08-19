<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('revisionable');
            $table->unsignedInteger('revision_number');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->string('checksum', 64);
            $table->string('event')->default('save');
            $table->foreignId('restored_from_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['revisionable_type', 'revisionable_id', 'revision_number'],
                'revisions_revisionable_number_unique',
            );
            $table->index(
                ['revisionable_type', 'revisionable_id', 'created_at'],
                'revisions_revisionable_created_index',
            );
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
