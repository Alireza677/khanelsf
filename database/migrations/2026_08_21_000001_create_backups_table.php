<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('google_drive_connection_id')->nullable()->constrained()->nullOnDelete();
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
            $table->string('drive_file_id')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_md5_checksum')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
