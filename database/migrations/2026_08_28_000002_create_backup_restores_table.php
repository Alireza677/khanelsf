<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backup_restores')) {
            return;
        }

        Schema::create('backup_restores', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('backup_id')->constrained('backups')->restrictOnDelete();
            $table->foreignId('safety_backup_id')->nullable()->constrained('backups')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->string('current_step')->nullable();
            $table->string('active_lock')->nullable()->unique();
            $table->string('failure_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restores');
    }
};
