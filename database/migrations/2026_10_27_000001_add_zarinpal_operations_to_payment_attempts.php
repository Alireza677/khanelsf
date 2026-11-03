<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            // "legacy" means unknown historical environment, never inferred bank verification.
            $table->string('environment', 16)->default('legacy');
            $table->timestamp('requested_at')->nullable();
            $table->string('operation', 16)->nullable();
            $table->char('operation_token', 64)->nullable();
            $table->timestamp('operation_expires_at')->nullable();
            $table->timestamp('notification_claimed_at')->nullable();
            $table->dropUnique(['provider', 'provider_reference']);
            $table->unique(['provider', 'environment', 'provider_reference'], 'payment_attempts_environment_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropUnique('payment_attempts_environment_reference_unique');
            $table->unique(['provider', 'provider_reference']);
            $table->dropColumn(['environment', 'requested_at', 'operation', 'operation_token', 'operation_expires_at', 'notification_claimed_at']);
        });
    }
};
