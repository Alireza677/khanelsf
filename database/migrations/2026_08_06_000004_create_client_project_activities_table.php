<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('activity_date');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('visibility')->default('internal')->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();

            $table->index(['client_project_id', 'activity_date']);
            $table->index(['activity_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_activities');
    }
};
