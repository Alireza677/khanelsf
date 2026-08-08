<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('display_name');
            $table->string('company_name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('customer_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('membership_role')->default('member');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['customer_id', 'user_id']);
            $table->index(['user_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user');
        Schema::dropIfExists('customers');
    }
};
