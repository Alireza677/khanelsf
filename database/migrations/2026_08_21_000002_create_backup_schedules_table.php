<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false)->index();
            $table->string('backup_type')->default('full');
            $table->string('interval_unit')->default('daily');
            $table->unsignedSmallInteger('interval_value')->default(1);
            $table->time('preferred_time')->nullable()->default('03:00');
            $table->string('timezone')->default('UTC');
            $table->unsignedSmallInteger('retention_count')->default(7);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('last_backup_id')->nullable();
            $table->string('last_result')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_summary')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('last_backup_id')->references('id')->on('backups')->nullOnDelete();
        });

        Schema::table('backups', function (Blueprint $table): void {
            $table->foreignId('backup_schedule_id')->nullable()->after('google_drive_connection_id')
                ->constrained('backup_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('backup_schedule_id');
        });
        Schema::dropIfExists('backup_schedules');
    }
};
