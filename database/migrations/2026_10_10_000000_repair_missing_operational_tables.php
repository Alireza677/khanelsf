<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('backups')) {
            // Final schema after removal of remote storage and automatic schedules.
            Schema::create('backups', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('type');
                $table->string('source');
                $table->string('status')->index();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('idempotency_key')->unique();
                $table->unsignedInteger('attempt')->default(0);
                $table->string('archive_name')->nullable();
                $table->string('archive_format')->default('zip');
                $table->unsignedSmallInteger('archive_version')->default(1);
                $table->unsignedSmallInteger('manifest_version')->default(1);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('checksum_algorithm')->nullable();
                $table->string('checksum')->nullable();
                $table->string('local_disk')->nullable();
                $table->text('local_path')->nullable();
                $table->string('failure_code')->nullable();
                $table->text('failure_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('backup_restores')) {
            Schema::create('backup_restores', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('backup_id')->constrained('backups')->restrictOnDelete();
                $table->foreignId('safety_backup_id')->nullable()->constrained('backups')->nullOnDelete();
                $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->index();
                $table->string('current_step')->nullable();
                $table->string('active_lock')->nullable()->unique();
                $table->char('progress_token_hash', 64)->nullable();
                $table->timestamp('progress_token_expires_at')->nullable();
                $table->string('failure_code')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // This repair cannot distinguish pre-existing tables from those it created.
        // Keep operational tables and their data intact when rolling back.
    }
};
