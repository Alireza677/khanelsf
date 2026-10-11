<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_work_item_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_project_work_item_id');
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at');
            $table->text('message')->nullable();
            $table->string('visibility', 16);
            $table->boolean('approval_required');
            $table->unsignedInteger('revision_allowance');
            $table->unique(['client_project_work_item_id', 'version'], 'work_delivery_version_unique');
            $table->foreign('client_project_work_item_id', 'work_delivery_item_fk')->references('id')->on('client_project_work_items')->restrictOnDelete();
            $table->foreign('submitted_by', 'work_delivery_actor_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('client_work_item_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_work_item_delivery_id')->unique('work_review_delivery_unique');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('decision', 24);
            $table->text('reason')->nullable();
            $table->timestamp('reviewed_at');
            $table->foreign('client_work_item_delivery_id', 'work_review_delivery_fk')->references('id')->on('client_work_item_deliveries')->restrictOnDelete();
            $table->foreign('reviewed_by', 'work_review_actor_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('client_delivery_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_work_item_delivery_id');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('visibility', 16);
            $table->timestamp('uploaded_at');
            $table->foreign('client_work_item_delivery_id', 'delivery_file_delivery_fk')->references('id')->on('client_work_item_deliveries')->restrictOnDelete();
            $table->foreign('uploaded_by', 'delivery_file_actor_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('client_cycle_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_project_cycle_id');
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('published');
            $table->string('outcome', 16);
            $table->json('snapshot');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at');
            $table->unique(['client_project_cycle_id', 'version'], 'cycle_report_version_unique');
            $table->foreign('client_project_cycle_id', 'cycle_report_cycle_fk')->references('id')->on('client_project_cycles')->restrictOnDelete();
            $table->foreign('published_by', 'cycle_report_actor_fk')->references('id')->on('users')->nullOnDelete();
        });
        // Existing events are internal until explicitly classified by the new writer.
        Schema::table('client_project_work_item_events', function (Blueprint $table) {
            $table->string('visibility', 16)->default('internal');
            $table->string('kind', 16)->default('internal');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_work_item_events', fn (Blueprint $table) => $table->dropColumn(['visibility', 'kind']));
        Schema::dropIfExists('client_cycle_reports');
        Schema::dropIfExists('client_delivery_files');
        Schema::dropIfExists('client_work_item_reviews');
        Schema::dropIfExists('client_work_item_deliveries');
    }
};
