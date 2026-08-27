<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('period_start')->index();
            $table->date('period_end');
            $table->char('currency', 3);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);
            $table->string('status')->default('draft')->index();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_project_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project_title_snapshot')->nullable();
            $table->string('activity_title_snapshot');
            $table->string('service_name_snapshot')->nullable();
            $table->string('service_unit_snapshot')->nullable();
            $table->string('service_unit_label_snapshot')->nullable();
            $table->string('pricing_mode_snapshot')->nullable();
            $table->text('description_snapshot')->nullable();
            $table->unsignedInteger('duration_minutes_snapshot')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('total_amount', 18, 2);
            $table->char('currency', 3);
            $table->date('activity_date_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_activity_claims', function (Blueprint $table): void {
            $table->foreignId('client_project_activity_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('invoice_number_sequences', function (Blueprint $table): void {
            $table->string('period_key')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_activity_claims');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_number_sequences');
    }
};
