<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_project_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('allocated_minutes');
            $table->string('status')->default('active')->index();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('invoiced_at')->nullable();
            $table->timestamps();
            $table->unique(['client_project_id', 'starts_at']);
        });
        Schema::table('client_project_activities', function (Blueprint $table): void {
            $table->foreignId('client_project_cycle_id')->nullable()->after('client_project_id')->constrained()->nullOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('client_project_cycle_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
        Schema::create('invoice_cycle_claims', function (Blueprint $table): void {
            $table->foreignId('client_project_cycle_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_cycle_claims');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('client_project_cycle_id'));
        Schema::table('client_project_activities', fn (Blueprint $table) => $table->dropConstrainedForeignId('client_project_cycle_id'));
        Schema::dropIfExists('client_project_cycles');
    }
};
