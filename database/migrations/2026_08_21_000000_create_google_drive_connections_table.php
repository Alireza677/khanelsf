<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_drive_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('google');
            $table->string('status')->index();
            $table->string('provider_account_id')->nullable();
            $table->string('display_email')->nullable();
            $table->longText('access_token')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_folder_name')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_summary')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_drive_connections');
    }
};
